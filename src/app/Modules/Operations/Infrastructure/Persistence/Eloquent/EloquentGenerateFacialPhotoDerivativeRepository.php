<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeException;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativePreparation;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeRepository;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeResult;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationResult;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeAttemptStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Infrastructure\Storage\FacialPhotoMediaCleanup;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final readonly class EloquentGenerateFacialPhotoDerivativeRepository implements GenerateFacialPhotoDerivativeRepository
{
    public function __construct(
        private FacialPhotoMediaCleanup $mediaCleanup
    ) {}

    public function prepare(
        GenerateFacialPhotoDerivativeCommand $command
    ): GenerateFacialPhotoDerivativePreparation {
        return DB::transaction(
            function () use (
                $command
            ): GenerateFacialPhotoDerivativePreparation {
                $photo = FacialPhotoRecord::query()
                    ->whereKey($command->photoId)
                    ->lockForUpdate()
                    ->first();

                if (! $photo instanceof FacialPhotoRecord) {
                    throw GenerateFacialPhotoDerivativeException::photoNotFound();
                }

                if (! $photo->isApproved()) {
                    throw GenerateFacialPhotoDerivativeException::photoNotApproved();
                }

                $sourceMedia = $photo->getFirstMedia(
                    FacialPhotoRecord::ORIGINAL_COLLECTION
                );

                if (! $sourceMedia instanceof Media) {
                    throw GenerateFacialPhotoDerivativeException::sourceUnavailable();
                }

                $absoluteSourcePath = $sourceMedia->getPath();

                if (
                    ! is_file($absoluteSourcePath)
                    || ! is_readable($absoluteSourcePath)
                ) {
                    throw GenerateFacialPhotoDerivativeException::sourceUnavailable();
                }

                $sourceSha256 = hash_file(
                    'sha256',
                    $absoluteSourcePath
                );

                if (
                    ! is_string($sourceSha256)
                    || preg_match(
                        '/\A[a-f0-9]{64}\z/',
                        $sourceSha256
                    ) !== 1
                ) {
                    throw GenerateFacialPhotoDerivativeException::sourceUnavailable();
                }

                if (
                    ! is_string($photo->sha256)
                    || ! hash_equals(
                        $photo->sha256,
                        $sourceSha256
                    )
                ) {
                    throw GenerateFacialPhotoDerivativeException::sourceChanged();
                }

                $derivative = FacialPhotoDerivativeRecord::query()
                    ->where(
                        'facial_photo_id',
                        $photo->getKey()
                    )
                    ->where(
                        'profile',
                        $command->profile->value
                    )
                    ->where(
                        'policy_version',
                        $command->policyVersion
                    )
                    ->where(
                        'source_sha256',
                        $sourceSha256
                    )
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $derivative instanceof FacialPhotoDerivativeRecord
                ) {
                    $derivative =
                        FacialPhotoDerivativeRecord::query()
                            ->create([
                                'facial_photo_id' => $photo->getKey(),
                                'tenant_id' => $photo->tenant_id,
                                'organization_id' => $photo->organization_id,
                                'profile' => $command->profile->value,
                                'policy_version' => $command->policyVersion,
                                'status' => FacialPhotoDerivativeStatus::Pending
                                    ->value,
                                'source_sha256' => $sourceSha256,
                            ]);
                }

                $readyResult = $this->readyResult(
                    $derivative
                );

                if (
                    $readyResult instanceof GenerateFacialPhotoDerivativeResult
                ) {
                    return GenerateFacialPhotoDerivativePreparation::reused(
                        derivativeId: (string) $derivative->getKey(),
                        sourceSha256: $sourceSha256,
                        result: $readyResult,
                    );
                }

                $derivative
                    ->attempts()
                    ->where(
                        'status',
                        FacialPhotoDerivativeAttemptStatus::Processing
                            ->value
                    )
                    ->update([
                        'status' => FacialPhotoDerivativeAttemptStatus::Failed
                            ->value,
                        'failure_code' => 'stale_attempt_replaced',
                        'finished_at' => now(),
                        'updated_at' => now(),
                    ]);

                $maximumAttempt = (int) (
                    $derivative
                        ->attempts()
                        ->max('attempt_number')
                    ?? 0
                );

                if ($maximumAttempt >= 65_535) {
                    throw GenerateFacialPhotoDerivativeException::attemptLimitReached();
                }

                $attempt = $derivative
                    ->attempts()
                    ->create([
                        'facial_photo_id' => $photo->getKey(),
                        'tenant_id' => $photo->tenant_id,
                        'organization_id' => $photo->organization_id,
                        'requested_by' => $command->requestedBy,
                        'requester_name' => $command->requesterName,
                        'attempt_number' => $maximumAttempt + 1,
                        'status' => FacialPhotoDerivativeAttemptStatus::Processing
                            ->value,
                        'normalizer' => $command->normalizer,
                        'normalizer_version' => $command->normalizerVersion,
                        'source_sha256' => $sourceSha256,
                        'started_at' => now(),
                    ]);

                $derivative->forceFill([
                    'status' => FacialPhotoDerivativeStatus::Processing
                        ->value,
                    'failed_at' => null,
                    'last_failure_code' => null,
                ])->save();

                return GenerateFacialPhotoDerivativePreparation::forAttempt(
                    derivativeId: (string) $derivative->getKey(),
                    attemptId: (string) $attempt->getKey(),
                    absoluteSourcePath: $absoluteSourcePath,
                    sourceSha256: $sourceSha256,
                );
            }
        );
    }

    public function complete(
        GenerateFacialPhotoDerivativePreparation $preparation,
        FacialPhotoNormalizationResult $normalization
    ): GenerateFacialPhotoDerivativeResult {
        $mediaCleanupReference = null;

        try {
            return DB::transaction(
                function () use (
                    $preparation,
                    $normalization,
                    &$mediaCleanupReference
                ): GenerateFacialPhotoDerivativeResult {
                    $derivative =
                        FacialPhotoDerivativeRecord::query()
                            ->whereKey(
                                $preparation->derivativeId
                            )
                            ->lockForUpdate()
                            ->first();

                    if (
                        ! $derivative instanceof FacialPhotoDerivativeRecord
                    ) {
                        throw GenerateFacialPhotoDerivativeException::persistenceFailed();
                    }

                    $attempt =
                        FacialPhotoDerivativeAttemptRecord::query()
                            ->whereKey(
                                $preparation->attemptId
                            )
                            ->lockForUpdate()
                            ->first();

                    if (
                        ! $attempt instanceof FacialPhotoDerivativeAttemptRecord
                        || $attempt->status
                            !== FacialPhotoDerivativeAttemptStatus::Processing
                    ) {
                        throw GenerateFacialPhotoDerivativeException::persistenceFailed();
                    }

                    $photo = FacialPhotoRecord::query()
                        ->whereKey(
                            $derivative->facial_photo_id
                        )
                        ->lockForUpdate()
                        ->first();

                    if (! $photo instanceof FacialPhotoRecord) {
                        throw GenerateFacialPhotoDerivativeException::photoNotFound();
                    }

                    if (! $photo->isApproved()) {
                        throw GenerateFacialPhotoDerivativeException::photoNotApproved();
                    }

                    $this->assertSourceStillMatches(
                        $photo,
                        $preparation->sourceSha256
                    );

                    $fileName = $this->safeFileName(
                        $derivative,
                        $normalization
                    );

                    $media = $photo
                        ->copyMedia(
                            $normalization->absolutePath
                        )
                        ->usingName(
                            pathinfo(
                                $fileName,
                                PATHINFO_FILENAME
                            )
                        )
                        ->usingFileName(
                            $fileName
                        )
                        ->toMediaCollection(
                            FacialPhotoRecord::DERIVATIVE_COLLECTION,
                            'facial_photos'
                        );

                    $mediaCleanupReference =
                        $this->mediaCleanup->reference(
                            $media
                        );

                    $this->assertPersistedMediaMatches(
                        $media,
                        $normalization
                    );

                    $completedAt = now();

                    $derivative->forceFill([
                        'status' => FacialPhotoDerivativeStatus::Ready
                            ->value,
                        'media_id' => $media->getKey(),
                        'width' => $normalization->width,
                        'height' => $normalization->height,
                        'mime_type' => $normalization->mimeType,
                        'size_bytes' => $normalization->sizeBytes,
                        'sha256' => $normalization->sha256,
                        'generated_at' => $completedAt,
                        'failed_at' => null,
                        'last_failure_code' => null,
                    ])->save();

                    $attempt->forceFill([
                        'status' => FacialPhotoDerivativeAttemptStatus::Succeeded
                            ->value,
                        'output_metadata' => $normalization->outputMetadata(),
                        'failure_code' => null,
                        'finished_at' => $completedAt,
                    ])->save();

                    FacialPhotoDerivativeRecord::query()
                        ->where(
                            'facial_photo_id',
                            $derivative->facial_photo_id
                        )
                        ->where(
                            'profile',
                            $derivative->profile
                        )
                        ->whereKeyNot(
                            $derivative->getKey()
                        )
                        ->where(
                            'status',
                            FacialPhotoDerivativeStatus::Ready
                                ->value
                        )
                        ->update([
                            'status' => FacialPhotoDerivativeStatus::Superseded
                                ->value,
                            'updated_at' => $completedAt,
                        ]);

                    return new GenerateFacialPhotoDerivativeResult(
                        derivativeId: (string) $derivative->getKey(),
                        attemptId: (string) $attempt->getKey(),
                        status: FacialPhotoDerivativeStatus::Ready,
                        reused: false,
                        mediaId: (int) $media->getKey(),
                        width: $normalization->width,
                        height: $normalization->height,
                        mimeType: $normalization->mimeType,
                        sizeBytes: $normalization->sizeBytes,
                        sha256: $normalization->sha256,
                    );
                }
            );
        } catch (Throwable $throwable) {
            try {
                $this->mediaCleanup->remove(
                    $mediaCleanupReference
                );
            } catch (Throwable) {
            }

            if (
                $throwable instanceof GenerateFacialPhotoDerivativeException
            ) {
                throw $throwable;
            }

            throw GenerateFacialPhotoDerivativeException::persistenceFailed(
                $throwable
            );
        }
    }

    public function fail(
        GenerateFacialPhotoDerivativePreparation $preparation,
        string $failureCode
    ): void {
        if (! $preparation->hasAttempt()) {
            return;
        }

        $failureCode = preg_match(
            '/\A[a-z0-9][a-z0-9._-]{0,79}\z/',
            $failureCode
        ) === 1
            ? $failureCode
            : 'generation_failed';

        DB::transaction(
            function () use (
                $preparation,
                $failureCode
            ): void {
                $attempt =
                    FacialPhotoDerivativeAttemptRecord::query()
                        ->whereKey(
                            $preparation->attemptId
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $attempt instanceof FacialPhotoDerivativeAttemptRecord
                    && $attempt->status
                        === FacialPhotoDerivativeAttemptStatus::Processing
                ) {
                    $attempt->forceFill([
                        'status' => FacialPhotoDerivativeAttemptStatus::Failed
                            ->value,
                        'failure_code' => $failureCode,
                        'finished_at' => now(),
                    ])->save();
                }

                $derivative =
                    FacialPhotoDerivativeRecord::query()
                        ->whereKey(
                            $preparation->derivativeId
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $derivative instanceof FacialPhotoDerivativeRecord
                    && $derivative->status
                        !== FacialPhotoDerivativeStatus::Ready
                ) {
                    $derivative->forceFill([
                        'status' => FacialPhotoDerivativeStatus::Failed
                            ->value,
                        'failed_at' => now(),
                        'last_failure_code' => $failureCode,
                    ])->save();
                }
            }
        );
    }

    private function readyResult(
        FacialPhotoDerivativeRecord $derivative
    ): ?GenerateFacialPhotoDerivativeResult {
        if (
            $derivative->status
                !== FacialPhotoDerivativeStatus::Ready
            || ! is_numeric($derivative->media_id)
            || ! is_string($derivative->sha256)
        ) {
            return null;
        }

        $media = Media::query()->find(
            $derivative->media_id
        );

        if (
            ! $media instanceof Media
            || $media->collection_name
                !== FacialPhotoRecord::DERIVATIVE_COLLECTION
            || $media->disk !== 'facial_photos'
        ) {
            return null;
        }

        if (
            ! $this->mediaMatches(
                $media,
                (int) $derivative->width,
                (int) $derivative->height,
                (string) $derivative->mime_type,
                (int) $derivative->size_bytes,
                $derivative->sha256
            )
        ) {
            return null;
        }

        $latestAttempt = $derivative
            ->attempts()
            ->where(
                'status',
                FacialPhotoDerivativeAttemptStatus::Succeeded
                    ->value
            )
            ->orderByDesc('attempt_number')
            ->first();

        return new GenerateFacialPhotoDerivativeResult(
            derivativeId: (string) $derivative->getKey(),
            attemptId: $latestAttempt instanceof FacialPhotoDerivativeAttemptRecord
                    ? (string) $latestAttempt->getKey()
                    : null,
            status: FacialPhotoDerivativeStatus::Ready,
            reused: true,
            mediaId: (int) $media->getKey(),
            width: (int) $derivative->width,
            height: (int) $derivative->height,
            mimeType: (string) $derivative->mime_type,
            sizeBytes: (int) $derivative->size_bytes,
            sha256: $derivative->sha256,
        );
    }

    private function assertSourceStillMatches(
        FacialPhotoRecord $photo,
        string $expectedSha256
    ): void {
        $media = $photo->getFirstMedia(
            FacialPhotoRecord::ORIGINAL_COLLECTION
        );

        if (! $media instanceof Media) {
            throw GenerateFacialPhotoDerivativeException::sourceUnavailable();
        }

        $absolutePath = $media->getPath();

        if (
            ! is_file($absolutePath)
            || ! is_readable($absolutePath)
        ) {
            throw GenerateFacialPhotoDerivativeException::sourceUnavailable();
        }

        $actualSha256 = hash_file(
            'sha256',
            $absolutePath
        );

        if (
            ! is_string($actualSha256)
            || ! hash_equals(
                $expectedSha256,
                $actualSha256
            )
            || ! is_string($photo->sha256)
            || ! hash_equals(
                $photo->sha256,
                $actualSha256
            )
        ) {
            throw GenerateFacialPhotoDerivativeException::sourceChanged();
        }
    }

    private function assertPersistedMediaMatches(
        Media $media,
        FacialPhotoNormalizationResult $normalization
    ): void {
        if (
            ! $this->mediaMatches(
                $media,
                $normalization->width,
                $normalization->height,
                $normalization->mimeType,
                $normalization->sizeBytes,
                $normalization->sha256
            )
        ) {
            throw GenerateFacialPhotoDerivativeException::persistedArtifactMismatch();
        }
    }

    private function mediaMatches(
        Media $media,
        int $expectedWidth,
        int $expectedHeight,
        string $expectedMimeType,
        int $expectedSizeBytes,
        string $expectedSha256,
    ): bool {
        $absolutePath = $media->getPath();

        if (
            ! is_file($absolutePath)
            || ! is_readable($absolutePath)
        ) {
            return false;
        }

        $information = @getimagesize(
            $absolutePath
        );

        $sizeBytes = filesize(
            $absolutePath
        );

        $sha256 = hash_file(
            'sha256',
            $absolutePath
        );

        return is_array($information)
            && (int) ($information[0] ?? 0)
                === $expectedWidth
            && (int) ($information[1] ?? 0)
                === $expectedHeight
            && ($information['mime'] ?? null)
                === $expectedMimeType
            && is_int($sizeBytes)
            && $sizeBytes === $expectedSizeBytes
            && is_string($sha256)
            && hash_equals(
                $expectedSha256,
                $sha256
            );
    }

    private function safeFileName(
        FacialPhotoDerivativeRecord $derivative,
        FacialPhotoNormalizationResult $normalization,
    ): string {
        $base = strtolower(
            preg_replace(
                '/[^a-z0-9]+/',
                '-',
                $derivative->profile
                    .'-'
                    .$derivative->policy_version
            ) ?? 'facial-derivative'
        );

        $base = trim(
            $base,
            '-'
        );

        if ($base === '') {
            $base = 'facial-derivative';
        }

        return substr(
            $base,
            0,
            100
        )
            .'-'
            .substr(
                $normalization->sha256,
                0,
                12
            )
            .'.jpg';
    }
}
