<?php

declare(strict_types=1);

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Schemas;

use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationAttemptStatus;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationAttemptRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

final class VisitorFacialCredentialSynchronizationPresentation
{
    /**
     * @return array{
     *     label: string,
     *     color: string
     * }
     */
    public static function summary(
        VisitorRecord $record
    ): array {
        [
            $photo,
            $derivative,
            $synchronizations,
        ] = self::resolve(
            $record
        );

        if (! $photo instanceof FacialPhotoRecord) {
            return [
                'label' => 'Não iniciada',
                'color' => 'gray',
            ];
        }

        if (
            self::photoStatus($photo->status)
                !== FacialPhotoStatus::Approved
        ) {
            return [
                'label' => 'Aguardando aprovação da foto',
                'color' => 'warning',
            ];
        }

        if (
            ! $derivative instanceof FacialPhotoDerivativeRecord
        ) {
            return [
                'label' => 'Aguardando preparação da foto',
                'color' => 'warning',
            ];
        }

        $derivativeStatus =
            self::derivativeStatus(
                $derivative->status
            );

        if (
            $derivativeStatus
                !== FacialPhotoDerivativeStatus::Ready
        ) {
            return match ($derivativeStatus) {
                FacialPhotoDerivativeStatus::Failed => [
                    'label' => 'Preparação da foto com falha',
                    'color' => 'danger',
                ],

                FacialPhotoDerivativeStatus::Processing => [
                    'label' => 'Preparando foto facial',
                    'color' => 'info',
                ],

                default => [
                    'label' => 'Aguardando preparação da foto',
                    'color' => 'warning',
                ],
            };
        }

        if ($synchronizations->isEmpty()) {
            return [
                'label' => 'Não iniciada',
                'color' => 'gray',
            ];
        }

        $statuses = $synchronizations
            ->map(
                static fn (
                    FacialCredentialSynchronizationRecord $synchronization
                ): ?FacialCredentialSynchronizationStatus => self::synchronizationStatus(
                    $synchronization->status
                )
            )
            ->filter();

        $deviceCount = $synchronizations
            ->pluck('access_device_id')
            ->filter(
                static fn (mixed $deviceId): bool => is_string($deviceId)
                    && trim($deviceId) !== ''
            )
            ->unique()
            ->count();

        if (
            $statuses->contains(
                FacialCredentialSynchronizationStatus::RequiresAttention
            )
        ) {
            return [
                'label' => 'Requer atenção',
                'color' => 'warning',
            ];
        }

        if (
            $statuses->contains(
                FacialCredentialSynchronizationStatus::Failed
            )
        ) {
            return [
                'label' => 'Falha na sincronização',
                'color' => 'danger',
            ];
        }

        if (
            $statuses->contains(
                FacialCredentialSynchronizationStatus::Blocked
            )
        ) {
            return [
                'label' => 'Sincronização bloqueada',
                'color' => 'warning',
            ];
        }

        if (
            $statuses->contains(
                FacialCredentialSynchronizationStatus::Processing
            )
        ) {
            return [
                'label' => 'Em processamento',
                'color' => 'info',
            ];
        }

        if (
            $statuses->contains(
                FacialCredentialSynchronizationStatus::Pending
            )
        ) {
            return [
                'label' => 'Aguardando sincronização',
                'color' => 'warning',
            ];
        }

        if (
            $statuses->isNotEmpty()
            && $statuses->every(
                static fn (
                    FacialCredentialSynchronizationStatus $status
                ): bool => $status
                    === FacialCredentialSynchronizationStatus::Succeeded
            )
        ) {
            return [
                'label' => $deviceCount <= 1
                        ? 'Sincronizada'
                        : sprintf(
                            'Sincronizada em %d dispositivos',
                            $deviceCount
                        ),
                'color' => 'success',
            ];
        }

        if (
            $statuses->contains(
                FacialCredentialSynchronizationStatus::Superseded
            )
        ) {
            return [
                'label' => 'Sincronização substituída',
                'color' => 'gray',
            ];
        }

        return [
            'label' => 'Situação indisponível',
            'color' => 'gray',
        ];
    }

    /**
     * @return list<string>
     */
    public static function details(
        VisitorRecord $record
    ): array {
        [
            $photo,
            $derivative,
            $synchronizations,
        ] = self::resolve(
            $record
        );

        if (
            ! $photo instanceof FacialPhotoRecord
            || self::photoStatus($photo->status)
                !== FacialPhotoStatus::Approved
        ) {
            return [];
        }

        if (
            ! $derivative instanceof FacialPhotoDerivativeRecord
            || self::derivativeStatus(
                $derivative->status
            ) !== FacialPhotoDerivativeStatus::Ready
        ) {
            return [
                'A sincronização somente poderá ser iniciada depois que a foto facial estiver preparada.',
            ];
        }

        if ($synchronizations->isEmpty()) {
            return [
                'Nenhuma intenção de sincronização foi criada para a foto facial atual.',
            ];
        }

        return $synchronizations
            ->map(
                static fn (
                    FacialCredentialSynchronizationRecord $synchronization
                ): string => self::synchronizationDetail(
                    $synchronization
                )
            )
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     0: ?FacialPhotoRecord,
     *     1: ?FacialPhotoDerivativeRecord,
     *     2: Collection<int, FacialCredentialSynchronizationRecord>
     * }
     */
    private static function resolve(
        VisitorRecord $record
    ): array {
        if (
            ! $record->relationLoaded(
                'latestFacialPhoto'
            )
        ) {
            $record->loadMissing(
                'latestFacialPhoto.derivatives'
            );
        }

        if (
            ! $record->relationLoaded(
                'facialCredentialSynchronizations'
            )
        ) {
            $record->loadMissing([
                'facialCredentialSynchronizations.accessDevice',
                'facialCredentialSynchronizations.latestAttempt',
            ]);
        }

        $photo = $record->latestFacialPhoto;

        if (! $photo instanceof FacialPhotoRecord) {
            return [
                null,
                null,
                new Collection,
            ];
        }

        if (
            ! $photo->relationLoaded(
                'derivatives'
            )
        ) {
            $photo->loadMissing(
                'derivatives'
            );
        }

        $derivative =
            self::currentDerivative(
                $photo
            );

        if (
            ! $derivative instanceof FacialPhotoDerivativeRecord
        ) {
            return [
                $photo,
                null,
                new Collection,
            ];
        }

        $synchronizations = $record->getRelation(
            'facialCredentialSynchronizations'
        );

        if (
            ! $synchronizations
                instanceof Collection
        ) {
            return [
                $photo,
                $derivative,
                new Collection,
            ];
        }

        $current = $synchronizations
            ->filter(
                static fn (
                    mixed $candidate
                ): bool => $candidate
                    instanceof FacialCredentialSynchronizationRecord
                    && (string) $candidate
                        ->facial_photo_id
                        === (string) $photo->getKey()
                    && (string) $candidate
                        ->facial_photo_derivative_id
                        === (string) $derivative->getKey()
                    && self::sameScope(
                        $record,
                        $candidate
                    )
            )
            ->sortByDesc(
                static fn (
                    FacialCredentialSynchronizationRecord $candidate
                ): string => sprintf(
                    '%020d|%020d|%s',
                    (int) $candidate->version,
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
                ): string => self::deviceLabel(
                    $candidate
                )
            )
            ->values();

        return [
            $photo,
            $derivative,
            $current,
        ];
    }

    private static function currentDerivative(
        FacialPhotoRecord $photo
    ): ?FacialPhotoDerivativeRecord {
        $derivatives = $photo->getRelation(
            'derivatives'
        );

        if (
            ! $derivatives instanceof Collection
        ) {
            return null;
        }

        $profile = (string) config(
            'facial_photos.normalization.default_profile',
            'vanguard_normalized'
        );

        $policyVersion = (string) config(
            'facial_photos.normalization.policy_version',
            'vanguard-normalization-v1'
        );

        $sourceSha256 =
            (string) $photo->sha256;

        $derivative = $derivatives
            ->filter(
                static fn (
                    mixed $candidate
                ): bool => $candidate
                    instanceof FacialPhotoDerivativeRecord
                    && $candidate->profile
                        === $profile
                    && $candidate->policy_version
                        === $policyVersion
                    && $candidate->source_sha256
                        === $sourceSha256
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

    private static function synchronizationDetail(
        FacialCredentialSynchronizationRecord $synchronization
    ): string {
        $status =
            self::synchronizationStatus(
                $synchronization->status
            );

        $parts = [
            self::deviceLabel(
                $synchronization
            ),

            $status?->label()
                ?? 'Situação indisponível',

            self::operationLabel(
                $synchronization->operation
            ),

            sprintf(
                'versão %d',
                max(
                    1,
                    (int) $synchronization
                        ->version
                )
            ),
        ];

        $attempt = self::latestAttempt(
            $synchronization
        );

        if (
            $attempt instanceof FacialCredentialSynchronizationAttemptRecord
        ) {
            $attemptStatus =
                self::attemptStatus(
                    $attempt->status
                );

            $parts[] = sprintf(
                'última tentativa %d: %s',
                max(
                    1,
                    (int) $attempt
                        ->attempt_number
                ),
                $attemptStatus?->label()
                    ?? 'Situação indisponível'
            );

            $provider = self::providerLabel(
                $attempt->provider
            );

            if ($provider !== null) {
                $parts[] =
                    'origem '.$provider;
            }

            $completedAt =
                self::formatDate(
                    $attempt->completed_at
                        ?? $attempt->started_at
                );

            if ($completedAt !== null) {
                $parts[] = $completedAt;
            }

            $message = self::safeMessage(
                $attempt->message
            );

            if ($message !== null) {
                $parts[] = $message;
            }
        }

        return implode(
            ' — ',
            array_filter($parts)
        ).'.';
    }

    private static function latestAttempt(
        FacialCredentialSynchronizationRecord $synchronization
    ): ?FacialCredentialSynchronizationAttemptRecord {
        if (
            ! $synchronization->relationLoaded(
                'latestAttempt'
            )
        ) {
            $synchronization->loadMissing(
                'latestAttempt'
            );
        }

        return $synchronization->latestAttempt
            instanceof FacialCredentialSynchronizationAttemptRecord
                ? $synchronization
                    ->latestAttempt
                : null;
    }

    private static function deviceLabel(
        FacialCredentialSynchronizationRecord $synchronization
    ): string {
        if (
            ! $synchronization->relationLoaded(
                'accessDevice'
            )
        ) {
            $synchronization->loadMissing(
                'accessDevice'
            );
        }

        $device =
            $synchronization->accessDevice;

        if (
            ! $device instanceof AccessDeviceRecord
        ) {
            return 'Dispositivo não localizado';
        }

        if (
            ! self::sameScope(
                $synchronization,
                $device
            )
        ) {
            return 'Dispositivo não localizado';
        }

        $displayName = trim(
            (string) $device->display_name
        );

        if ($displayName !== '') {
            return $displayName;
        }

        $model = trim(
            (string) $device->model
        );

        return $model !== ''
            ? $model
            : 'Dispositivo sem identificação';
    }

    private static function operationLabel(
        mixed $operation
    ): string {
        return match (
            trim(
                (string) $operation
            )
        ) {
            'register' => 'Cadastro',
            'replace' => 'Substituição',
            default => 'Operação não identificada',
        };
    }

    private static function providerLabel(
        mixed $provider
    ): ?string {
        return match (
            strtolower(
                trim(
                    (string) $provider
                )
            )
        ) {
            'disabled' => 'desativada',
            'simulator' => 'simulador',
            'vanguard' => 'validação interna',
            default => null,
        };
    }

    private static function safeMessage(
        mixed $message
    ): ?string {
        if (! is_string($message)) {
            return null;
        }

        $message = preg_replace(
            '/[\x00-\x1F\x7F]+/u',
            ' ',
            trim($message)
        );

        if (
            ! is_string($message)
            || $message === ''
        ) {
            return null;
        }

        return mb_strimwidth(
            $message,
            0,
            180,
            '…'
        );
    }

    private static function formatDate(
        mixed $value
    ): ?string {
        if (
            ! $value instanceof CarbonInterface
        ) {
            return null;
        }

        return $value->format(
            'd/m/Y H:i:s'
        );
    }

    private static function sameScope(
        object $left,
        object $right,
    ): bool {
        $leftTenantId = trim(
            (string) ($left->tenant_id ?? '')
        );

        $rightTenantId = trim(
            (string) ($right->tenant_id ?? '')
        );

        $leftOrganizationId = trim(
            (string) ($left->organization_id ?? '')
        );

        $rightOrganizationId = trim(
            (string) ($right->organization_id ?? '')
        );

        if (
            $leftTenantId === ''
            || $rightTenantId === ''
            || $leftOrganizationId === ''
            || $rightOrganizationId === ''
        ) {
            return false;
        }

        return $leftTenantId === $rightTenantId
            && $leftOrganizationId
                === $rightOrganizationId;
    }

    private static function photoStatus(
        mixed $status
    ): ?FacialPhotoStatus {
        return $status
            instanceof FacialPhotoStatus
                ? $status
                : FacialPhotoStatus::tryFrom(
                    (string) $status
                );
    }

    private static function derivativeStatus(
        mixed $status
    ): ?FacialPhotoDerivativeStatus {
        return $status
            instanceof FacialPhotoDerivativeStatus
                ? $status
                : FacialPhotoDerivativeStatus::tryFrom(
                    (string) $status
                );
    }

    private static function synchronizationStatus(
        mixed $status
    ): ?FacialCredentialSynchronizationStatus {
        return $status
            instanceof FacialCredentialSynchronizationStatus
                ? $status
                : FacialCredentialSynchronizationStatus::tryFrom(
                    (string) $status
                );
    }

    private static function attemptStatus(
        mixed $status
    ): ?FacialCredentialSynchronizationAttemptStatus {
        return $status
            instanceof FacialCredentialSynchronizationAttemptStatus
                ? $status
                : FacialCredentialSynchronizationAttemptStatus::tryFrom(
                    (string) $status
                );
    }
}
