<?php

declare(strict_types=1);

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions;

use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

final class VisitorFacialCredentialSynchronizationExecutionEligibility
{
    public static function hasExecutable(
        VisitorRecord $visitor
    ): bool {
        return self::synchronizations(
            $visitor
        )->isNotEmpty();
    }

    public static function resolve(
        VisitorRecord $visitor,
        string $synchronizationId
    ): ?FacialCredentialSynchronizationRecord {
        $synchronizationId = trim(
            $synchronizationId
        );

        if ($synchronizationId === '') {
            return null;
        }

        $synchronization = self::synchronizations(
            $visitor
        )->first(
            static fn (
                FacialCredentialSynchronizationRecord $candidate
            ): bool => (string) $candidate->getKey()
                === $synchronizationId
        );

        return $synchronization
            instanceof FacialCredentialSynchronizationRecord
                ? $synchronization
                : null;
    }

    /**
     * @return Collection<int, FacialCredentialSynchronizationRecord>
     */
    public static function synchronizations(
        VisitorRecord $visitor
    ): Collection {
        self::loadRequiredRelations(
            $visitor
        );

        $photo = $visitor->latestFacialPhoto;

        if (
            ! $photo instanceof FacialPhotoRecord
            || self::photoStatus(
                $photo->status
            ) !== FacialPhotoStatus::Approved
        ) {
            return new Collection;
        }

        $derivative = self::currentDerivative(
            $photo
        );

        if (
            ! $derivative instanceof FacialPhotoDerivativeRecord
        ) {
            return new Collection;
        }

        $synchronizations = $visitor->getRelation(
            'facialCredentialSynchronizations'
        );

        if (! $synchronizations instanceof Collection) {
            return new Collection;
        }

        return $synchronizations
            ->filter(
                static fn (
                    mixed $candidate
                ): bool => $candidate
                    instanceof FacialCredentialSynchronizationRecord
                    && self::isCurrentPendingSynchronization(
                        visitor: $visitor,
                        photo: $photo,
                        derivative: $derivative,
                        synchronization: $candidate,
                    )
            )
            ->sortByDesc(
                static fn (
                    FacialCredentialSynchronizationRecord $candidate
                ): string => sprintf(
                    '%020d|%020d|%s',
                    max(
                        1,
                        (int) $candidate->version
                    ),
                    $candidate->created_at
                        instanceof CarbonInterface
                            ? $candidate
                                ->created_at
                                ->getTimestamp()
                            : 0,
                    (string) $candidate->getKey()
                )
            )
            ->unique(
                static fn (
                    FacialCredentialSynchronizationRecord $candidate
                ): string => sprintf(
                    '%s|%s',
                    (string) $candidate
                        ->access_device_id,
                    (string) $candidate
                        ->operation
                )
            )
            ->sortBy(
                static fn (
                    FacialCredentialSynchronizationRecord $candidate
                ): string => sprintf(
                    '%s|%s|%s',
                    (string) $candidate
                        ->access_device_id,
                    (string) $candidate
                        ->operation,
                    (string) $candidate
                        ->getKey()
                )
            )
            ->values();
    }

    private static function loadRequiredRelations(
        VisitorRecord $visitor
    ): void {
        if (
            ! $visitor->relationLoaded(
                'latestFacialPhoto'
            )
        ) {
            $visitor->loadMissing(
                'latestFacialPhoto.derivatives'
            );
        }

        if (
            ! $visitor->relationLoaded(
                'facialCredentialSynchronizations'
            )
        ) {
            $visitor->loadMissing(
                'facialCredentialSynchronizations'
            );
        }
    }

    private static function currentDerivative(
        FacialPhotoRecord $photo
    ): ?FacialPhotoDerivativeRecord {
        if (
            ! $photo->relationLoaded(
                'derivatives'
            )
        ) {
            $photo->loadMissing(
                'derivatives'
            );
        }

        $derivatives = $photo->getRelation(
            'derivatives'
        );

        if (! $derivatives instanceof Collection) {
            return null;
        }

        $profile = (string) config(
            'facial_photos.intelbras_derivative.profile',
            'intelbras_facial_credential'
        );

        $policyVersion = (string) config(
            'facial_photos.intelbras_derivative.policy_version',
            'intelbras-facial-credential-v1'
        );

        $sourceSha256 = (string) $photo->sha256;

        $derivative = $derivatives
            ->filter(
                static fn (
                    mixed $candidate
                ): bool => $candidate
                    instanceof FacialPhotoDerivativeRecord
                    && $candidate->profile === $profile
                    && $candidate->policy_version
                        === $policyVersion
                    && $candidate->source_sha256
                        === $sourceSha256
                    && self::derivativeStatus(
                        $candidate->status
                    ) === FacialPhotoDerivativeStatus::Ready
            )
            ->sortByDesc(
                static fn (
                    FacialPhotoDerivativeRecord $candidate
                ): string => sprintf(
                    '%020d|%s',
                    $candidate->created_at
                        instanceof CarbonInterface
                            ? $candidate
                                ->created_at
                                ->getTimestamp()
                            : 0,
                    (string) $candidate->getKey()
                )
            )
            ->first();

        return $derivative
            instanceof FacialPhotoDerivativeRecord
                ? $derivative
                : null;
    }

    private static function isCurrentPendingSynchronization(
        VisitorRecord $visitor,
        FacialPhotoRecord $photo,
        FacialPhotoDerivativeRecord $derivative,
        FacialCredentialSynchronizationRecord $synchronization
    ): bool {
        return self::sameScope(
            $visitor,
            $synchronization
        )
            && (string) $synchronization->visitor_id
                === (string) $visitor->getKey()
            && (string) $synchronization->facial_photo_id
                === (string) $photo->getKey()
            && (string) $synchronization
                ->facial_photo_derivative_id
                === (string) $derivative->getKey()
            && self::synchronizationStatus(
                $synchronization->status
            ) === FacialCredentialSynchronizationStatus::Pending;
    }

    private static function sameScope(
        VisitorRecord $visitor,
        FacialCredentialSynchronizationRecord $synchronization
    ): bool {
        $visitorTenantId = trim(
            (string) $visitor->tenant_id
        );

        $visitorOrganizationId = trim(
            (string) $visitor->organization_id
        );

        return $visitorTenantId !== ''
            && $visitorOrganizationId !== ''
            && (string) $synchronization->tenant_id
                === $visitorTenantId
            && (string) $synchronization->organization_id
                === $visitorOrganizationId;
    }

    private static function photoStatus(
        mixed $status
    ): ?FacialPhotoStatus {
        if ($status instanceof FacialPhotoStatus) {
            return $status;
        }

        return is_string($status)
            ? FacialPhotoStatus::tryFrom(
                $status
            )
            : null;
    }

    private static function derivativeStatus(
        mixed $status
    ): ?FacialPhotoDerivativeStatus {
        if (
            $status instanceof FacialPhotoDerivativeStatus
        ) {
            return $status;
        }

        return is_string($status)
            ? FacialPhotoDerivativeStatus::tryFrom(
                $status
            )
            : null;
    }

    private static function synchronizationStatus(
        mixed $status
    ): ?FacialCredentialSynchronizationStatus {
        if (
            $status
                instanceof FacialCredentialSynchronizationStatus
        ) {
            return $status;
        }

        return is_string($status)
            ? FacialCredentialSynchronizationStatus::tryFrom(
                $status
            )
            : null;
    }
}
