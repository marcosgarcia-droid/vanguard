<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationException;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationRepository;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationResult;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationPersistenceData;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationTarget;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class EloquentExecuteFacialPhotoValidationRepository implements ExecuteFacialPhotoValidationRepository
{
    private const MAX_ATTEMPTS = 65_535;

    public function findTarget(
        string $photoId
    ): ?FacialPhotoValidationTarget {
        $photo = FacialPhotoRecord::query()
            ->find($photoId);

        if (! $photo instanceof FacialPhotoRecord) {
            return null;
        }

        $source = $this->validatedSourceMedia(
            $photo
        );

        return new FacialPhotoValidationTarget(
            photoId: (string) $photo->getKey(),
            status: $this->photoStatus(
                $photo,
                duringPersistence: false,
            ),
            mediaId: (int) $source['media']->getKey(),
            absolutePath: $source['absolute_path'],
            sha256: $source['sha256'],
        );
    }

    public function persist(
        FacialPhotoValidationPersistenceData $data
    ): ExecuteFacialPhotoValidationResult {
        return DB::transaction(
            function () use (
                $data
            ): ExecuteFacialPhotoValidationResult {
                $photo = FacialPhotoRecord::query()
                    ->whereKey(
                        $data->target->photoId
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $photo instanceof FacialPhotoRecord) {
                    throw ExecuteFacialPhotoValidationException::photoNotFound();
                }

                $currentStatus = $this->photoStatus(
                    $photo,
                    duringPersistence: true,
                );

                if (
                    $currentStatus
                    !== FacialPhotoStatus::PendingValidation
                ) {
                    throw ExecuteFacialPhotoValidationException::statusNotEligible(
                        $currentStatus
                    );
                }

                $source = $this->validatedSourceMedia(
                    $photo
                );

                if (
                    (int) $source['media']->getKey()
                        !== $data->target->mediaId
                    || $source['absolute_path']
                        !== $data->target->absolutePath
                    || ! hash_equals(
                        $data->target->sha256,
                        $source['sha256']
                    )
                ) {
                    throw ExecuteFacialPhotoValidationException::sourceMediaChanged();
                }

                [
                    $operatorUserId,
                    $operatorName,
                ] = $this->operatorSnapshot(
                    $data->operatorUserId
                );

                $latestAttempt =
                    FacialPhotoValidationAttemptRecord::query()
                        ->where(
                            'facial_photo_id',
                            $photo->id
                        )
                        ->orderByDesc(
                            'attempt_number'
                        )
                        ->lockForUpdate()
                        ->first([
                            'attempt_number',
                        ]);

                $nextAttemptNumber =
                    (
                        $latestAttempt
                            ?->attempt_number
                        ?? 0
                    ) + 1;

                if (
                    $nextAttemptNumber
                    > self::MAX_ATTEMPTS
                ) {
                    throw ExecuteFacialPhotoValidationException::attemptLimitReached();
                }

                $validatedAt = now()->toImmutable();

                $attempt =
                    FacialPhotoValidationAttemptRecord::query()
                        ->create([
                            'facial_photo_id' => $photo->id,
                            'tenant_id' => $photo->tenant_id,
                            'organization_id' => $photo->organization_id,
                            'operator_user_id' => $operatorUserId,
                            'operator_name' => $operatorName,
                            'attempt_number' => $nextAttemptNumber,
                            'validator' => $data
                                ->validation
                                ->validator,
                            'validator_version' => $data
                                ->validation
                                ->version,
                            'decision' => $data
                                ->validation
                                ->decision,
                            'face_count' => $data
                                ->validation
                                ->faceCount,
                            'metrics' => $data
                                ->validation
                                ->metrics,
                            'issues' => $data
                                ->validation
                                ->issueCodes(),
                            'status_before' => $currentStatus,
                            'status_after' => $data
                                ->transition
                                ->to,
                            'validated_at' => $validatedAt,
                        ]);

                $nextStatus =
                    $data->transition->to;

                /*
                 * Os campos validation_version, validation_result,
                 * rejection_reasons e analyzed_at pertencem à
                 * análise técnica do original e são preservados.
                 * O resultado facial completo permanece no ledger.
                 */
                $photo
                    ->forceFill([
                        'status' => $nextStatus,
                        'approved_at' => $nextStatus
                                === FacialPhotoStatus::Approved
                                    ? $validatedAt
                                    : null,
                        'rejected_at' => $nextStatus
                                === FacialPhotoStatus::Rejected
                                    ? $validatedAt
                                    : null,
                    ])
                    ->save();

                return new ExecuteFacialPhotoValidationResult(
                    photoId: (string) $photo->getKey(),
                    attemptId: (string) $attempt->getKey(),
                    attemptNumber: $nextAttemptNumber,
                    validation: $data->validation,
                    transition: $data->transition,
                    validatedAt: $validatedAt,
                );
            },
            3
        );
    }

    /**
     * @return array{
     *     media: Media,
     *     absolute_path: string,
     *     sha256: string
     * }
     */
    private function validatedSourceMedia(
        FacialPhotoRecord $photo
    ): array {
        $media = $photo->getFirstMedia(
            FacialPhotoRecord::ORIGINAL_COLLECTION
        );

        if (! $media instanceof Media) {
            throw ExecuteFacialPhotoValidationException::sourceMediaUnavailable();
        }

        $absolutePath = $media->getPath();

        if (
            ! is_file($absolutePath)
            || ! is_readable($absolutePath)
        ) {
            throw ExecuteFacialPhotoValidationException::sourceMediaUnavailable();
        }

        $storedSha256 = strtolower(
            trim(
                (string) $photo->sha256
            )
        );

        if (
            preg_match(
                '/\A[a-f0-9]{64}\z/',
                $storedSha256
            ) !== 1
        ) {
            throw ExecuteFacialPhotoValidationException::sourceMediaUnavailable();
        }

        $currentSha256 = hash_file(
            'sha256',
            $absolutePath
        );

        if (
            ! is_string($currentSha256)
            || preg_match(
                '/\A[a-f0-9]{64}\z/',
                $currentSha256
            ) !== 1
        ) {
            throw ExecuteFacialPhotoValidationException::sourceMediaUnavailable();
        }

        if (
            ! hash_equals(
                $storedSha256,
                $currentSha256
            )
        ) {
            throw ExecuteFacialPhotoValidationException::sourceMediaChanged();
        }

        return [
            'media' => $media,
            'absolute_path' => $absolutePath,
            'sha256' => $currentSha256,
        ];
    }

    /**
     * @return array{0: ?int, 1: ?string}
     */
    private function operatorSnapshot(
        ?int $operatorUserId
    ): array {
        if ($operatorUserId === null) {
            return [
                null,
                null,
            ];
        }

        $operator = User::query()
            ->whereKey($operatorUserId)
            ->lockForUpdate()
            ->first();

        if (! $operator instanceof User) {
            throw ExecuteFacialPhotoValidationException::operatorNotFound();
        }

        $operatorName = trim(
            (string) $operator->name
        );

        if ($operatorName === '') {
            $operatorName = trim(
                (string) $operator->email
            );
        }

        if ($operatorName === '') {
            $operatorName =
                'Usuário #'.$operator->id;
        }

        return [
            (int) $operator->id,
            $operatorName,
        ];
    }

    private function photoStatus(
        FacialPhotoRecord $photo,
        bool $duringPersistence,
    ): FacialPhotoStatus {
        $status = $photo->status;

        if ($status instanceof FacialPhotoStatus) {
            return $status;
        }

        $resolved = FacialPhotoStatus::tryFrom(
            (string) $photo->getRawOriginal(
                'status'
            )
        );

        if ($resolved instanceof FacialPhotoStatus) {
            return $resolved;
        }

        if ($duringPersistence) {
            throw ExecuteFacialPhotoValidationException::persistenceFailed();
        }

        throw ExecuteFacialPhotoValidationException::preparationFailed();
    }
}
