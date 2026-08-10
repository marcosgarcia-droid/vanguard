<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationCommand;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationReason;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationRepository;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationResult;
use App\Modules\Operations\Application\FacialCredentials\Create\FacialCredentialSynchronizationContext;
use App\Modules\Operations\Application\FacialCredentials\Create\FacialCredentialSynchronizationPreparation;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSubjectType;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use BackedEnum;
use Illuminate\Support\Facades\DB;

final class EloquentCreateFacialCredentialSynchronizationRepository implements CreateFacialCredentialSynchronizationRepository
{
    public function prepare(
        CreateFacialCredentialSynchronizationCommand $command
    ): FacialCredentialSynchronizationPreparation {
        $subject = $this->resolveSubject(
            $command->subjectType,
            $command->subjectId,
        );

        if ($subject === null) {
            return FacialCredentialSynchronizationPreparation::blocked(
                match ($command->subjectType) {
                    FacialCredentialSubjectType::Visitor => CreateFacialCredentialSynchronizationReason::VisitorNotFound,
                    FacialCredentialSubjectType::Employee => CreateFacialCredentialSynchronizationReason::ContextChanged,
                }
            );
        }

        if ($this->enumValue($subject->status) !== 'active') {
            return FacialCredentialSynchronizationPreparation::blocked(
                match ($command->subjectType) {
                    FacialCredentialSubjectType::Visitor => CreateFacialCredentialSynchronizationReason::VisitorInactive,
                    FacialCredentialSubjectType::Employee => CreateFacialCredentialSynchronizationReason::ContextChanged,
                }
            );
        }

        $device = AccessDeviceRecord::query()->find(
            $command->accessDeviceId
        );

        if (! $device instanceof AccessDeviceRecord) {
            return FacialCredentialSynchronizationPreparation::blocked(
                CreateFacialCredentialSynchronizationReason::DeviceNotFound
            );
        }

        if ($device->status !== AccessDeviceStatus::Active) {
            return FacialCredentialSynchronizationPreparation::blocked(
                CreateFacialCredentialSynchronizationReason::DeviceInactive
            );
        }

        if (! $this->isSupportedDevice($device)) {
            return FacialCredentialSynchronizationPreparation::blocked(
                CreateFacialCredentialSynchronizationReason::UnsupportedDevice
            );
        }

        if (! $this->sameScope($subject, $device)) {
            return FacialCredentialSynchronizationPreparation::blocked(
                CreateFacialCredentialSynchronizationReason::ScopeMismatch
            );
        }

        $photo = $this->currentPhoto(
            $subject
        );

        if (! $photo instanceof FacialPhotoRecord) {
            return FacialCredentialSynchronizationPreparation::blocked(
                CreateFacialCredentialSynchronizationReason::CurrentPhotoMissing
            );
        }

        if ($photo->status !== FacialPhotoStatus::Approved) {
            return FacialCredentialSynchronizationPreparation::blocked(
                CreateFacialCredentialSynchronizationReason::CurrentPhotoNotApproved
            );
        }

        if (
            ! $this->sameScope($subject, $photo)
            || ! $this->validSha256($photo->sha256)
        ) {
            return FacialCredentialSynchronizationPreparation::blocked(
                CreateFacialCredentialSynchronizationReason::InvalidPhotoMetadata
            );
        }

        $derivative = $this->latestReadyDerivative(
            $photo
        );

        if (
            ! $derivative instanceof FacialPhotoDerivativeRecord
        ) {
            return FacialCredentialSynchronizationPreparation::blocked(
                CreateFacialCredentialSynchronizationReason::ReadyDerivativeMissing
            );
        }

        if (
            ! $this->sameScope($subject, $derivative)
            || ! $this->validDerivativeMetadata(
                $derivative
            )
        ) {
            return FacialCredentialSynchronizationPreparation::blocked(
                CreateFacialCredentialSynchronizationReason::InvalidDerivativeMetadata
            );
        }

        $snapshot = $this->latestSuccessfulSnapshot(
            $device
        );

        if (
            ! $snapshot instanceof AccessDeviceConfigurationSnapshotRecord
            || ! $this->sameScope($subject, $snapshot)
            || ! $this->deviceModelMatchesSnapshot(
                $device,
                $snapshot
            )
        ) {
            return FacialCredentialSynchronizationPreparation::blocked(
                CreateFacialCredentialSynchronizationReason::SuccessfulConfigurationSnapshotMissing
            );
        }

        return FacialCredentialSynchronizationPreparation::ready(
            new FacialCredentialSynchronizationContext(
                tenantId: (string) $subject->tenant_id,
                organizationId: (string) $subject->organization_id,
                subjectType: $command->subjectType,
                subjectId: (string) $subject->getKey(),
                subjectDisplayName: trim((string) $subject->display_name),
                externalUserId: (string) $subject->getKey(),
                facialPhotoId: (string) $photo->getKey(),
                facialPhotoDerivativeId: (string) $derivative->getKey(),
                accessDeviceId: (string) $device->getKey(),
                configurationSnapshotId: (string) $snapshot->getKey(),
                deviceModel: trim((string) $snapshot->device_model),
                firmwareVersion: trim((string) $snapshot->firmware_version),
                derivativeSha256: (string) $derivative->sha256,
                derivativeSizeBytes: (int) $derivative->size_bytes,
                derivativeWidth: (int) $derivative->width,
                derivativeHeight: (int) $derivative->height,
                derivativeMimeType: (string) $derivative->mime_type,
            )
        );
    }

    public function persist(
        FacialCredentialSynchronizationContext $context,
        IntelbrasFacialCredentialOperation $operation,
        string $planFingerprint,
        string $contextFingerprint,
    ): CreateFacialCredentialSynchronizationResult {
        if (
            ! $this->validSha256($planFingerprint)
            || ! $this->validSha256($contextFingerprint)
        ) {
            return CreateFacialCredentialSynchronizationResult::blocked(
                CreateFacialCredentialSynchronizationReason::ContextChanged
            );
        }

        $subjectMorphType = $this->subjectMorphType(
            $context->subjectType
        );

        if ($subjectMorphType === null) {
            return CreateFacialCredentialSynchronizationResult::blocked(
                CreateFacialCredentialSynchronizationReason::ContextChanged
            );
        }

        return DB::transaction(
            function () use (
                $context,
                $operation,
                $planFingerprint,
                $contextFingerprint,
                $subjectMorphType
            ): CreateFacialCredentialSynchronizationResult {
                if (! $this->contextStillCurrent($context)) {
                    return CreateFacialCredentialSynchronizationResult::blocked(
                        CreateFacialCredentialSynchronizationReason::ContextChanged
                    );
                }

                $existing =
                    FacialCredentialSynchronizationRecord::query()
                        ->where(
                            'context_fingerprint',
                            $contextFingerprint
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $existing instanceof FacialCredentialSynchronizationRecord
                ) {
                    if (
                        ! $this->existingRecordMatches(
                            $existing,
                            $context,
                            $operation,
                            $planFingerprint
                        )
                    ) {
                        return CreateFacialCredentialSynchronizationResult::blocked(
                            CreateFacialCredentialSynchronizationReason::ContextChanged
                        );
                    }

                    return CreateFacialCredentialSynchronizationResult::reused(
                        synchronizationId: (string) $existing->getKey(),
                        version: (int) $existing->version,
                    );
                }

                $latest =
                    FacialCredentialSynchronizationRecord::query()
                        ->where(
                            'subject_type',
                            $subjectMorphType
                        )
                        ->where(
                            'subject_id',
                            $context->subjectId
                        )
                        ->where(
                            'access_device_id',
                            $context->accessDeviceId
                        )
                        ->where(
                            'operation',
                            $operation->value
                        )
                        ->orderByDesc('version')
                        ->lockForUpdate()
                        ->first();

                $version =
                    ($latest instanceof FacialCredentialSynchronizationRecord
                        ? (int) $latest->version
                        : 0) + 1;

                FacialCredentialSynchronizationRecord::query()
                    ->where(
                        'subject_type',
                        $subjectMorphType
                    )
                    ->where(
                        'subject_id',
                        $context->subjectId
                    )
                    ->where(
                        'access_device_id',
                        $context->accessDeviceId
                    )
                    ->where(
                        'operation',
                        $operation->value
                    )
                    ->where(
                        'status',
                        FacialCredentialSynchronizationStatus::Pending
                            ->value
                    )
                    ->update([
                        'status' => FacialCredentialSynchronizationStatus::Superseded
                            ->value,
                        'updated_at' => now(),
                    ]);

                $record =
                    FacialCredentialSynchronizationRecord::query()
                        ->create([
                            'tenant_id' => $context->tenantId,
                            'organization_id' => $context->organizationId,
                            'subject_type' => $subjectMorphType,
                            'subject_id' => $context->subjectId,
                            'visitor_id' => $context->subjectType
                                === FacialCredentialSubjectType::Visitor
                                    ? $context->subjectId
                                    : null,
                            'facial_photo_id' => $context->facialPhotoId,
                            'facial_photo_derivative_id' => $context->facialPhotoDerivativeId,
                            'access_device_id' => $context->accessDeviceId,
                            'operation' => $operation->value,
                            'status' => FacialCredentialSynchronizationStatus::Pending,
                            'version' => $version,
                            'plan_fingerprint' => $planFingerprint,
                            'context_fingerprint' => $contextFingerprint,
                        ]);

                return CreateFacialCredentialSynchronizationResult::created(
                    synchronizationId: (string) $record->getKey(),
                    version: $version,
                );
            },
            3
        );
    }

    private function contextStillCurrent(
        FacialCredentialSynchronizationContext $context
    ): bool {
        $subject = match ($context->subjectType) {
            FacialCredentialSubjectType::Visitor => VisitorRecord::query()
                ->whereKey($context->subjectId)
                ->lockForUpdate()
                ->first(),
            FacialCredentialSubjectType::Employee => EmployeeRecord::query()
                ->whereKey($context->subjectId)
                ->lockForUpdate()
                ->first(),
        };

        $device =
            AccessDeviceRecord::query()
                ->whereKey($context->accessDeviceId)
                ->lockForUpdate()
                ->first();

        if (
            (
                ! $subject instanceof VisitorRecord
                && ! $subject instanceof EmployeeRecord
            )
            || ! $device instanceof AccessDeviceRecord
            || $this->enumValue($subject->status) !== 'active'
            || $device->status !== AccessDeviceStatus::Active
            || ! $this->isSupportedDevice($device)
            || ! $this->sameScope($subject, $device)
            || (string) $subject->tenant_id
                !== $context->tenantId
            || (string) $subject->organization_id
                !== $context->organizationId
            || trim((string) $subject->display_name)
                !== $context->subjectDisplayName
            || (string) $subject->getKey()
                !== $context->externalUserId
        ) {
            return false;
        }

        $photo = $this->currentPhoto(
            $subject,
            lockForUpdate: true
        );

        if (
            ! $photo instanceof FacialPhotoRecord
            || (string) $photo->getKey()
                !== $context->facialPhotoId
            || $photo->status !== FacialPhotoStatus::Approved
            || ! $this->sameScope($subject, $photo)
            || ! $this->validSha256($photo->sha256)
        ) {
            return false;
        }

        $derivative = $this->latestReadyDerivative(
            $photo,
            lockForUpdate: true
        );

        if (
            ! $derivative instanceof FacialPhotoDerivativeRecord
            || (string) $derivative->getKey()
                !== $context->facialPhotoDerivativeId
            || ! $this->sameScope($subject, $derivative)
            || ! $this->validDerivativeMetadata(
                $derivative
            )
            || ! hash_equals(
                $context->derivativeSha256,
                (string) $derivative->sha256
            )
            || (int) $derivative->size_bytes
                !== $context->derivativeSizeBytes
            || (int) $derivative->width
                !== $context->derivativeWidth
            || (int) $derivative->height
                !== $context->derivativeHeight
            || (string) $derivative->mime_type
                !== $context->derivativeMimeType
        ) {
            return false;
        }

        $snapshot = $this->latestSuccessfulSnapshot(
            $device,
            lockForUpdate: true
        );

        return $snapshot instanceof AccessDeviceConfigurationSnapshotRecord
            && (string) $snapshot->getKey()
                === $context->configurationSnapshotId
            && $this->sameScope($subject, $snapshot)
            && $this->deviceModelMatchesSnapshot(
                $device,
                $snapshot
            )
            && trim((string) $snapshot->device_model)
                === $context->deviceModel
            && trim((string) $snapshot->firmware_version)
                === $context->firmwareVersion;
    }

    private function resolveSubject(
        FacialCredentialSubjectType $subjectType,
        string $subjectId,
    ): EmployeeRecord|VisitorRecord|null {
        return match ($subjectType) {
            FacialCredentialSubjectType::Visitor => VisitorRecord::query()->find($subjectId),
            FacialCredentialSubjectType::Employee => EmployeeRecord::query()->find($subjectId),
        };
    }

    private function currentPhoto(
        EmployeeRecord|VisitorRecord $subject,
        bool $lockForUpdate = false,
    ): ?FacialPhotoRecord {
        $query = $subject
            ->facialPhotos()
            ->orderByDesc('captured_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $photo = $query->first();

        return $photo instanceof FacialPhotoRecord
            ? $photo
            : null;
    }

    private function latestReadyDerivative(
        FacialPhotoRecord $photo,
        bool $lockForUpdate = false,
    ): ?FacialPhotoDerivativeRecord {
        if (! $this->validSha256($photo->sha256)) {
            return null;
        }

        $query = $photo
            ->derivatives()
            ->where(
                'profile',
                (string) config(
                    'facial_photos.intelbras_derivative.profile',
                    'intelbras_facial_credential'
                )
            )
            ->where(
                'policy_version',
                (string) config(
                    'facial_photos.intelbras_derivative.policy_version',
                    'intelbras-facial-credential-v1'
                )
            )
            ->where(
                'status',
                FacialPhotoDerivativeStatus::Ready->value
            )
            ->where(
                'source_sha256',
                (string) $photo->sha256
            )
            ->orderByDesc('generated_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $derivative = $query->first();

        return $derivative instanceof FacialPhotoDerivativeRecord
                ? $derivative
                : null;
    }

    private function latestSuccessfulSnapshot(
        AccessDeviceRecord $device,
        bool $lockForUpdate = false,
    ): ?AccessDeviceConfigurationSnapshotRecord {
        $query =
            AccessDeviceConfigurationSnapshotRecord::query()
                ->where(
                    'access_device_id',
                    $device->getKey()
                )
                ->where(
                    'status',
                    AccessDeviceConfigurationReadStatus::Success
                        ->value
                )
                ->whereNotNull('device_model')
                ->whereNotNull('firmware_version')
                ->where('device_model', '<>', '')
                ->where('firmware_version', '<>', '')
                ->orderByDesc('read_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $snapshot = $query->first();

        return $snapshot instanceof AccessDeviceConfigurationSnapshotRecord
                ? $snapshot
                : null;
    }

    private function existingRecordMatches(
        FacialCredentialSynchronizationRecord $record,
        FacialCredentialSynchronizationContext $context,
        IntelbrasFacialCredentialOperation $operation,
        string $planFingerprint,
    ): bool {
        $subjectMorphType = $this->subjectMorphType(
            $context->subjectType
        );

        if ($subjectMorphType === null) {
            return false;
        }

        $legacyVisitorId =
            $context->subjectType
                === FacialCredentialSubjectType::Visitor
                    ? $context->subjectId
                    : null;

        return (string) $record->tenant_id
                === $context->tenantId
            && (string) $record->organization_id
                === $context->organizationId
            && (string) $record->subject_type
                === $subjectMorphType
            && (string) $record->subject_id
                === $context->subjectId
            && (
                $legacyVisitorId === null
                    ? $record->visitor_id === null
                    : (string) $record->visitor_id
                        === $legacyVisitorId
            )
            && (string) $record->facial_photo_id
                === $context->facialPhotoId
            && (string) $record->facial_photo_derivative_id
                === $context->facialPhotoDerivativeId
            && (string) $record->access_device_id
                === $context->accessDeviceId
            && (string) $record->operation
                === $operation->value
            && hash_equals(
                (string) $record->plan_fingerprint,
                $planFingerprint
            );
    }

    private function subjectMorphType(
        FacialCredentialSubjectType $subjectType
    ): ?string {
        return match ($subjectType) {
            FacialCredentialSubjectType::Visitor => VisitorRecord::class,
            FacialCredentialSubjectType::Employee => EmployeeRecord::class,
        };
    }

    private function validDerivativeMetadata(
        FacialPhotoDerivativeRecord $derivative
    ): bool {
        return $derivative->status
                === FacialPhotoDerivativeStatus::Ready
            && $this->validSha256(
                $derivative->source_sha256
            )
            && $this->validSha256(
                $derivative->sha256
            )
            && (int) $derivative->size_bytes > 0
            && (int) $derivative->width > 0
            && (int) $derivative->height > 0
            && trim(
                (string) $derivative->mime_type
            ) !== '';
    }

    private function isSupportedDevice(
        AccessDeviceRecord $device
    ): bool {
        return strtolower(
            trim((string) $device->provider)
        ) === 'intelbras'
            && strtolower(
                trim((string) $device->device_type)
            ) === 'facial_reader';
    }

    private function deviceModelMatchesSnapshot(
        AccessDeviceRecord $device,
        AccessDeviceConfigurationSnapshotRecord $snapshot,
    ): bool {
        $deviceModel = preg_replace(
            '/\s+/u',
            ' ',
            trim((string) $device->model)
        );

        $snapshotModel = preg_replace(
            '/\s+/u',
            ' ',
            trim((string) $snapshot->device_model)
        );

        return is_string($deviceModel)
            && is_string($snapshotModel)
            && $deviceModel !== ''
            && $snapshotModel !== ''
            && strtoupper($deviceModel)
                === strtoupper($snapshotModel);
    }

    private function sameScope(
        object $left,
        object $right,
    ): bool {
        return (string) ($left->tenant_id ?? '')
                === (string) ($right->tenant_id ?? '')
            && (string) ($left->organization_id ?? '')
                === (string) ($right->organization_id ?? '');
    }

    private function enumValue(
        mixed $value
    ): string {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return trim((string) $value);
    }

    private function validSha256(
        mixed $value
    ): bool {
        return is_string($value)
            && preg_match(
                '/^[a-f0-9]{64}$/D',
                $value
            ) === 1;
    }
}
