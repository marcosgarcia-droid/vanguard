<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoCommand;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoException;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoRepository;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoResult;
use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\AnalyzeFacialPhotoUseCase;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSubjectType;
use App\Modules\Operations\Infrastructure\Storage\FacialPhotoMediaCleanup;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class EloquentRegisterFacialPhotoRepository implements RegisterFacialPhotoRepository
{
    public function __construct(
        private AnalyzeFacialPhotoUseCase $analyzeFacialPhoto,
        private FacialPhotoMediaCleanup $mediaCleanup,
    ) {}

    public function register(
        RegisterFacialPhotoCommand $command
    ): RegisterFacialPhotoResult {
        if (
            ! is_file($command->absolutePath)
            || ! is_readable($command->absolutePath)
        ) {
            throw RegisterFacialPhotoException::sourceFileUnavailable();
        }

        /**
         * O rollback do banco não remove a mídia copiada fisicamente.
         *
         * @var array{
         *     disk: string,
         *     directory: string
         * }|null
         */
        $mediaCleanupReference = null;

        try {
            return DB::transaction(
                function () use (
                    $command,
                    &$mediaCleanupReference
                ): RegisterFacialPhotoResult {
                    $subject = $this->resolveSubject(
                        $command
                    );

                    $photo = $subject
                        ->facialPhotos()
                        ->create([
                            'tenant_id' => $subject->tenant_id,
                            'organization_id' => $subject->organization_id,
                            'created_by' => $command->createdBy,
                            'source' => $command->source->value,
                            'status' => FacialPhotoStatus::PendingValidation->value,
                            'captured_at' => $command->capturedAt
                                ?? now(),
                        ]);

                    $fileName = $this->safeFileName(
                        $command
                    );

                    $media = $photo
                        ->copyMedia(
                            $command->absolutePath
                        )
                        ->usingName(
                            pathinfo(
                                $fileName,
                                PATHINFO_FILENAME
                            )
                        )
                        ->usingFileName($fileName)
                        ->toMediaCollection(
                            FacialPhotoRecord::ORIGINAL_COLLECTION,
                            'facial_photos'
                        );

                    $mediaCleanupReference =
                        $this->mediaCleanup->reference(
                            $media
                        );

                    $definitiveFingerprint =
                        $this->definitiveFingerprint(
                            $media->getPath()
                        );

                    if (
                        ! hash_equals(
                            $command->expectedSha256,
                            $definitiveFingerprint
                        )
                    ) {
                        throw RegisterFacialPhotoException::definitiveFingerprintMismatch();
                    }

                    $analysis =
                        $this->analyzeFacialPhoto
                            ->execute(
                                $media->getPath()
                            );

                    $analyzedAt = now();

                    $status = $analysis->passed
                        ? FacialPhotoStatus::PendingValidation
                        : FacialPhotoStatus::Rejected;

                    $metrics = $analysis->metrics;

                    $photo->forceFill([
                        'status' => $status->value,
                        'analyzed_at' => $analyzedAt,
                        'approved_at' => null,
                        'rejected_at' => $status
                            === FacialPhotoStatus::Rejected
                                ? $analyzedAt
                                : null,
                        'outdated_at' => null,
                        'width' => $this->integerMetric(
                            $metrics,
                            'width'
                        ),
                        'height' => $this->integerMetric(
                            $metrics,
                            'height'
                        ),
                        'mime_type' => $this->stringMetric(
                            $metrics,
                            'mime_type'
                        ),
                        'size_bytes' => $this->integerMetric(
                            $metrics,
                            'size_bytes'
                        ),
                        'sha256' => $definitiveFingerprint,
                        'validation_version' => $analysis->version,
                        'validation_result' => $analysis->toArray(),
                        'rejection_reasons' => $analysis->issueCodes(),
                    ])->save();

                    FacialPhotoConfirmationConsumptionRecord::query()
                        ->create([
                            'facial_photo_id' => $photo->getKey(),
                            'subject_type' => $subject::class,
                            'subject_id' => $subject->getKey(),
                            'visitor_id' => $subject
                                instanceof VisitorRecord
                                    ? $subject->getKey()
                                    : null,
                            'tenant_id' => $subject->tenant_id,
                            'organization_id' => $subject->organization_id,
                            'confirmed_by' => $command->createdBy,
                            'confirmation_key' => $command->confirmationKey,
                            'confirmation_context' => $command->confirmationContext,
                            'photo_sha256' => $definitiveFingerprint,
                            'consumed_at' => $analyzedAt,
                        ]);

                    return new RegisterFacialPhotoResult(
                        photoId: (string) $photo->getKey(),
                        status: $status,
                        technicalAnalysis: $analysis,
                    );
                }
            );
        } catch (Throwable $exception) {
            $this->mediaCleanup->remove(
                $mediaCleanupReference
            );

            if (
                $exception
                instanceof RegisterFacialPhotoException
            ) {
                throw $exception;
            }

            if (
                $exception instanceof QueryException
                && $this->isConfirmationConsumptionConflict(
                    $exception
                )
            ) {
                throw RegisterFacialPhotoException::confirmationAlreadyConsumed(
                    $exception
                );
            }

            throw RegisterFacialPhotoException::registrationFailed(
                $exception
            );
        }
    }

    private function resolveSubject(
        RegisterFacialPhotoCommand $command
    ): EmployeeRecord|VisitorRecord {
        return match ($command->subjectType) {
            FacialPhotoSubjectType::Visitor => $this->visitorSubject(
                $command->subjectId
            ),

            FacialPhotoSubjectType::Employee => $this->employeeSubject(
                $command->subjectId
            ),
        };
    }

    private function visitorSubject(
        string $subjectId
    ): VisitorRecord {
        $visitor = VisitorRecord::query()
            ->whereKey($subjectId)
            ->lockForUpdate()
            ->first();

        if (! $visitor instanceof VisitorRecord) {
            throw RegisterFacialPhotoException::subjectNotFound();
        }

        return $visitor;
    }

    private function employeeSubject(
        string $subjectId
    ): EmployeeRecord {
        $employee = EmployeeRecord::query()
            ->whereKey($subjectId)
            ->lockForUpdate()
            ->first();

        if (! $employee instanceof EmployeeRecord) {
            throw RegisterFacialPhotoException::subjectNotFound();
        }

        if ((string) $employee->status !== 'active') {
            throw RegisterFacialPhotoException::subjectUnavailable();
        }

        return $employee;
    }

    private function isConfirmationConsumptionConflict(
        QueryException $exception
    ): bool {
        $message = strtolower(
            $exception->getMessage()
        );

        return str_contains(
            $message,
            'fpcc_confirmation_unique'
        )
            || str_contains(
                $message,
                'fpcc_photo_unique'
            )
            || str_contains(
                $message,
                'facial_photo_confirmation_consumptions.confirmation_key'
            )
            || str_contains(
                $message,
                'facial_photo_confirmation_consumptions.facial_photo_id'
            );
    }

    private function definitiveFingerprint(
        string $absolutePath
    ): string {
        $fingerprint = hash_file(
            'sha256',
            $absolutePath
        );

        if (
            ! is_string($fingerprint)
            || preg_match(
                '/\A[a-f0-9]{64}\z/',
                $fingerprint
            ) !== 1
        ) {
            throw RegisterFacialPhotoException::definitiveFingerprintUnavailable();
        }

        return $fingerprint;
    }

    private function safeFileName(
        RegisterFacialPhotoCommand $command
    ): string {
        $originalFileName = basename(
            trim($command->originalFileName)
        );

        $allowedExtensions = [
            'jpg',
            'jpeg',
            'png',
            'webp',
        ];

        $originalExtension = strtolower(
            pathinfo(
                $originalFileName,
                PATHINFO_EXTENSION
            )
        );

        $sourceExtension = strtolower(
            pathinfo(
                $command->absolutePath,
                PATHINFO_EXTENSION
            )
        );

        $extension = in_array(
            $originalExtension,
            $allowedExtensions,
            true
        )
            ? $originalExtension
            : $sourceExtension;

        if (
            ! in_array(
                $extension,
                $allowedExtensions,
                true
            )
        ) {
            $extension = 'jpg';
        }

        $baseName = Str::slug(
            pathinfo(
                $originalFileName,
                PATHINFO_FILENAME
            ),
            '-'
        );

        $baseName = Str::limit(
            $baseName,
            80,
            ''
        );

        if (blank($baseName)) {
            $baseName = 'facial-photo';
        }

        return "{$baseName}.{$extension}";
    }

    /**
     * @param  array<string, int|float|string|null>  $metrics
     */
    private function integerMetric(
        array $metrics,
        string $key
    ): ?int {
        $value = $metrics[$key] ?? null;

        return is_int($value)
            ? $value
            : null;
    }

    /**
     * @param  array<string, int|float|string|null>  $metrics
     */
    private function stringMetric(
        array $metrics,
        string $key
    ): ?string {
        $value = $metrics[$key] ?? null;

        return is_string($value)
            ? $value
            : null;
    }
}
