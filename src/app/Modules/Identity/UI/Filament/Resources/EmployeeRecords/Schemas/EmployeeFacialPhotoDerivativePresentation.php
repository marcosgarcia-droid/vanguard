<?php

declare(strict_types=1);

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Schemas;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeAttemptStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeAttemptRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use Carbon\CarbonInterface;

final class EmployeeFacialPhotoDerivativePresentation
{
    /**
     * @return array{
     *     label: string,
     *     color: string
     * }
     */
    public static function summary(
        EmployeeRecord $record
    ): array {
        [
            $photo,
            $derivative,
        ] = self::resolve(
            $record
        );

        if (! $photo instanceof FacialPhotoRecord) {
            return [
                'label' => 'Não iniciada',
                'color' => 'gray',
            ];
        }

        $photoStatus = self::photoStatus(
            $photo->status
        );

        if ($photoStatus !== FacialPhotoStatus::Approved) {
            return match ($photoStatus) {
                FacialPhotoStatus::PendingValidation => [
                    'label' => 'Aguardando aprovação da foto',
                    'color' => 'warning',
                ],
                FacialPhotoStatus::Rejected => [
                    'label' => 'Foto reprovada',
                    'color' => 'danger',
                ],
                FacialPhotoStatus::Outdated => [
                    'label' => 'Foto desatualizada',
                    'color' => 'gray',
                ],
                default => [
                    'label' => 'Não iniciada',
                    'color' => 'gray',
                ],
            };
        }

        if (
            ! $derivative instanceof FacialPhotoDerivativeRecord
        ) {
            return self::automaticGenerationEnabled()
                ? [
                    'label' => 'Aguardando preparação',
                    'color' => 'warning',
                ]
                : [
                    'label' => 'Preparação desativada',
                    'color' => 'gray',
                ];
        }

        $status = self::derivativeStatus(
            $derivative->status
        );

        return match ($status) {
            FacialPhotoDerivativeStatus::Pending => [
                'label' => $status->label(),
                'color' => 'warning',
            ],
            FacialPhotoDerivativeStatus::Processing => [
                'label' => $status->label(),
                'color' => 'info',
            ],
            FacialPhotoDerivativeStatus::Ready => [
                'label' => $status->label(),
                'color' => 'success',
            ],
            FacialPhotoDerivativeStatus::Failed => [
                'label' => $status->label(),
                'color' => 'danger',
            ],
            FacialPhotoDerivativeStatus::Superseded => [
                'label' => $status->label(),
                'color' => 'gray',
            ],
            default => [
                'label' => 'Situação indisponível',
                'color' => 'gray',
            ],
        };
    }

    /**
     * @return list<string>
     */
    public static function details(
        EmployeeRecord $record
    ): array {
        [
            $photo,
            $derivative,
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
        ) {
            return [
                self::automaticGenerationEnabled()
                    ? 'A preparação ainda não foi iniciada pela fila de processamento.'
                    : 'A preparação automática está desativada neste ambiente.',
            ];
        }

        $details = [];
        $attempt = self::latestAttempt(
            $derivative
        );

        self::appendDerivativeDetails(
            $details,
            $derivative
        );

        self::appendAttemptDetails(
            $details,
            $attempt
        );

        self::appendFailureDetails(
            $details,
            $derivative,
            $attempt
        );

        return array_values(
            array_unique(
                array_filter(
                    $details
                )
            )
        );
    }

    /**
     * @return array{
     *     0: ?FacialPhotoRecord,
     *     1: ?FacialPhotoDerivativeRecord
     * }
     */
    private static function resolve(
        EmployeeRecord $record
    ): array {
        if (
            ! $record->relationLoaded(
                'latestFacialPhoto'
            )
        ) {
            $record->loadMissing(
                'latestFacialPhoto.derivatives.latestAttempt'
            );
        }

        $photo = $record->latestFacialPhoto;

        if (! $photo instanceof FacialPhotoRecord) {
            return [
                null,
                null,
            ];
        }

        if (
            ! $photo->relationLoaded(
                'derivatives'
            )
        ) {
            $photo->loadMissing(
                'derivatives.latestAttempt'
            );
        }

        $profile = (string) config(
            'facial_photos.normalization.default_profile',
            'vanguard_normalized'
        );

        $policyVersion = (string) config(
            'facial_photos.normalization.policy_version',
            'vanguard-normalization-v1'
        );

        $derivative = $photo
            ->derivatives
            ->filter(
                static fn (
                    mixed $candidate
                ): bool => $candidate instanceof FacialPhotoDerivativeRecord
                    && $candidate->profile === $profile
                    && $candidate->policy_version
                        === $policyVersion
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

        return [
            $photo,
            $derivative instanceof FacialPhotoDerivativeRecord
                    ? $derivative
                    : null,
        ];
    }

    private static function latestAttempt(
        FacialPhotoDerivativeRecord $derivative
    ): ?FacialPhotoDerivativeAttemptRecord {
        if (
            ! $derivative->relationLoaded(
                'latestAttempt'
            )
        ) {
            $derivative->loadMissing(
                'latestAttempt'
            );
        }

        return $derivative->latestAttempt
            instanceof FacialPhotoDerivativeAttemptRecord
                ? $derivative->latestAttempt
                : null;
    }

    /**
     * @param  list<string>  $details
     */
    private static function appendDerivativeDetails(
        array &$details,
        FacialPhotoDerivativeRecord $derivative
    ): void {
        $status = self::derivativeStatus(
            $derivative->status
        );

        if (
            $status === FacialPhotoDerivativeStatus::Ready
        ) {
            $generatedAt = self::formatDate(
                $derivative->generated_at
            );

            if ($generatedAt !== null) {
                $details[] =
                    'Preparação concluída em '
                    .$generatedAt.'.';
            }

            $imageDetails = collect([
                self::dimensions(
                    $derivative
                ),
                self::mimeTypeLabel(
                    $derivative->mime_type
                ),
                self::formatBytes(
                    $derivative->size_bytes
                ),
            ])
                ->filter()
                ->implode(' — ');

            if ($imageDetails !== '') {
                $details[] =
                    'Imagem preparada: '
                    .$imageDetails.'.';
            }
        }

        if (
            $status
                === FacialPhotoDerivativeStatus::Processing
        ) {
            $details[] =
                'A imagem está sendo preparada pela fila de processamento.';
        }

        if (
            $status
                === FacialPhotoDerivativeStatus::Pending
        ) {
            $details[] =
                'A preparação aguarda o início do processamento.';
        }

        if (
            $status
                === FacialPhotoDerivativeStatus::Superseded
        ) {
            $details[] =
                'Esta preparação foi substituída por uma versão mais recente.';
        }
    }

    /**
     * @param  list<string>  $details
     */
    private static function appendAttemptDetails(
        array &$details,
        ?FacialPhotoDerivativeAttemptRecord $attempt
    ): void {
        if (
            ! $attempt instanceof FacialPhotoDerivativeAttemptRecord
        ) {
            return;
        }

        $status = self::attemptStatus(
            $attempt->status
        );

        $details[] = sprintf(
            'Última tentativa: %d — %s.',
            (int) $attempt->attempt_number,
            $status?->label()
                ?: 'Situação indisponível'
        );

        $startedAt = self::formatDate(
            $attempt->started_at
        );

        if ($startedAt !== null) {
            $details[] =
                'Processamento iniciado em '
                .$startedAt.'.';
        }

        $finishedAt = self::formatDate(
            $attempt->finished_at
        );

        if ($finishedAt !== null) {
            $details[] =
                'Processamento finalizado em '
                .$finishedAt.'.';
        }

        if (
            is_string($attempt->requester_name)
            && trim($attempt->requester_name) !== ''
        ) {
            $details[] =
                'Solicitada por '
                .trim($attempt->requester_name)
                .'.';
        }
    }

    /**
     * @param  list<string>  $details
     */
    private static function appendFailureDetails(
        array &$details,
        FacialPhotoDerivativeRecord $derivative,
        ?FacialPhotoDerivativeAttemptRecord $attempt
    ): void {
        if (
            self::derivativeStatus(
                $derivative->status
            ) !== FacialPhotoDerivativeStatus::Failed
        ) {
            return;
        }

        $failureCode = null;

        if (
            $attempt instanceof FacialPhotoDerivativeAttemptRecord
            && is_string($attempt->failure_code)
            && trim($attempt->failure_code) !== ''
        ) {
            $failureCode =
                trim($attempt->failure_code);
        } elseif (
            is_string($derivative->last_failure_code)
            && trim($derivative->last_failure_code)
                !== ''
        ) {
            $failureCode =
                trim($derivative->last_failure_code);
        }

        $details[] =
            'Motivo: '
            .self::friendlyFailure(
                $failureCode
            );
    }

    private static function friendlyFailure(
        ?string $failureCode
    ): string {
        return match ($failureCode) {
            'generation_in_progress' => 'a preparação já está em andamento.',

            'photo_not_found' => 'a foto facial não foi localizada.',

            'photo_not_approved' => 'a foto precisa estar aprovada antes da preparação.',

            'source_unavailable',
            'file_unavailable' => 'o arquivo original não está disponível. Atualize a foto facial.',

            'source_changed' => 'o arquivo original foi alterado. Atualize a foto facial.',

            'attempt_limit_reached' => 'o limite de tentativas foi atingido. Solicite suporte técnico.',

            'normalization_disabled' => 'a preparação automática está desativada neste ambiente.',

            'source_too_large',
            'original_file_too_large' => 'o arquivo original excede o tamanho permitido.',

            'unsupported_format' => 'o formato da imagem não é suportado.',

            'pixel_limit_exceeded' => 'as dimensões da imagem excedem o limite permitido.',

            'decode_failed' => 'a imagem original não pôde ser lida.',

            'temporary_directory_unavailable' => 'o espaço temporário de processamento está indisponível.',

            'output_write_failed' => 'não foi possível gravar a imagem preparada.',

            'output_too_large' => 'a imagem preparada excedeu o tamanho permitido.',

            'invalid_output',
            'invalid_normalizer_output' => 'o resultado da preparação não é válido.',

            'persisted_artifact_mismatch' => 'a imagem armazenada não corresponde ao resultado processado.',

            'stale_attempt_replaced' => 'uma tentativa anterior foi substituída com segurança.',

            'persistence_failed' => 'não foi possível registrar o resultado da preparação.',

            'processing_failed',
            'generation_failed' => 'não foi possível concluir a preparação da imagem.',

            default => 'não foi possível concluir a preparação. Tente novamente ou solicite suporte.',
        };
    }

    private static function automaticGenerationEnabled(): bool
    {
        return (bool) config(
            'facial_photos.normalization.enabled',
            false
        )
            && (bool) config(
                'facial_photos.normalization.async_generation.enabled',
                false
            );
    }

    private static function photoStatus(
        mixed $status
    ): ?FacialPhotoStatus {
        return $status instanceof FacialPhotoStatus
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

    private static function attemptStatus(
        mixed $status
    ): ?FacialPhotoDerivativeAttemptStatus {
        return $status
            instanceof FacialPhotoDerivativeAttemptStatus
                ? $status
                : FacialPhotoDerivativeAttemptStatus::tryFrom(
                    (string) $status
                );
    }

    private static function dimensions(
        FacialPhotoDerivativeRecord $derivative
    ): ?string {
        if (
            ! is_numeric($derivative->width)
            || ! is_numeric($derivative->height)
            || (int) $derivative->width < 1
            || (int) $derivative->height < 1
        ) {
            return null;
        }

        return sprintf(
            '%d × %d px',
            (int) $derivative->width,
            (int) $derivative->height
        );
    }

    private static function mimeTypeLabel(
        mixed $mimeType
    ): ?string {
        if (! is_string($mimeType)) {
            return null;
        }

        return match (strtolower(trim($mimeType))) {
            'image/jpeg' => 'JPEG',
            'image/png' => 'PNG',
            'image/webp' => 'WebP',
            default => null,
        };
    }

    private static function formatBytes(
        mixed $size
    ): ?string {
        if (
            ! is_numeric($size)
            || (int) $size < 1
        ) {
            return null;
        }

        $bytes = (int) $size;

        if ($bytes >= 1_048_576) {
            return number_format(
                $bytes / 1_048_576,
                2,
                ',',
                '.'
            ).' MB';
        }

        if ($bytes >= 1_024) {
            return number_format(
                $bytes / 1_024,
                1,
                ',',
                '.'
            ).' KB';
        }

        return $bytes.' bytes';
    }

    private static function formatDate(
        mixed $date
    ): ?string {
        return $date instanceof CarbonInterface
            ? $date->format('d/m/Y H:i:s')
            : null;
    }
}
