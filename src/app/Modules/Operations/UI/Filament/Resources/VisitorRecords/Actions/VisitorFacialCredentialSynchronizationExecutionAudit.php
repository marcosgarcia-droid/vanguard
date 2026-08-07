<?php

declare(strict_types=1);

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationResult;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationAttemptStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use BackedEnum;
use Closure;
use Throwable;

final class VisitorFacialCredentialSynchronizationExecutionAudit
{
    public static function record(
        VisitorRecord $visitor,
        User $user,
        FacialCredentialSynchronizationRecord $synchronization,
        ExecuteFacialCredentialSynchronizationResult $result,
    ): void {
        self::safely(
            static function () use (
                $visitor,
                $user,
                $synchronization,
                $result,
            ): void {
                activity('visitor_management')
                    ->causedBy($user)
                    ->performedOn($visitor)
                    ->event(
                        self::event($result)
                    )
                    ->withProperties(
                        self::properties(
                            synchronization: $synchronization,
                            result: $result,
                        )
                    )
                    ->log(
                        self::description($result)
                    );
            }
        );
    }

    public static function failure(
        VisitorRecord $visitor,
        User $user,
        ?FacialCredentialSynchronizationRecord $synchronization,
    ): void {
        self::safely(
            static function () use (
                $visitor,
                $user,
                $synchronization,
            ): void {
                activity('visitor_management')
                    ->causedBy($user)
                    ->performedOn($visitor)
                    ->event(
                        'visitor_facial_credential_synchronization_execution_failed'
                    )
                    ->withProperties(
                        self::failureProperties(
                            $synchronization
                        )
                    )
                    ->log(
                        'Falha na execução da sincronização facial'
                    );
            }
        );
    }

    public static function event(
        ExecuteFacialCredentialSynchronizationResult $result
    ): string {
        if (! $result->reason->isSuccessful()) {
            return 'visitor_facial_credential_synchronization_execution_not_performed';
        }

        return match ($result->status) {
            FacialCredentialSynchronizationAttemptStatus::Succeeded => 'visitor_facial_credential_synchronization_execution_succeeded',

            FacialCredentialSynchronizationAttemptStatus::RequiresAttention => 'visitor_facial_credential_synchronization_execution_requires_attention',

            FacialCredentialSynchronizationAttemptStatus::Blocked => 'visitor_facial_credential_synchronization_execution_blocked',

            FacialCredentialSynchronizationAttemptStatus::Failed => 'visitor_facial_credential_synchronization_execution_failed',

            FacialCredentialSynchronizationAttemptStatus::Pending,
            FacialCredentialSynchronizationAttemptStatus::Processing,
            null => 'visitor_facial_credential_synchronization_execution_not_performed',
        };
    }

    public static function description(
        ExecuteFacialCredentialSynchronizationResult $result
    ): string {
        return match (self::event($result)) {
            'visitor_facial_credential_synchronization_execution_succeeded' => 'Sincronização facial simulada concluída',

            'visitor_facial_credential_synchronization_execution_requires_attention' => 'Sincronização facial simulada requer atenção',

            'visitor_facial_credential_synchronization_execution_blocked' => 'Execução da sincronização facial bloqueada',

            'visitor_facial_credential_synchronization_execution_failed' => 'Falha na execução da sincronização facial',

            default => 'Sincronização facial não executada',
        };
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public static function properties(
        FacialCredentialSynchronizationRecord $synchronization,
        ExecuteFacialCredentialSynchronizationResult $result,
    ): array {
        $device = self::device(
            $synchronization
        );

        return self::withoutEmptyValues([
            'resultado' => $result->reason->label(),

            'situação' => $result->status?->label()
                ?? 'Não executada',

            'tentativa' => $result->attemptNumber,

            'reutilizado' => $result->wasReused()
                ? 'Sim'
                : 'Não',

            'origem' => self::providerLabel(
                $result->provider
            ),

            'cenário' => self::scenarioLabel(
                $result->scenario
            ),

            'dispositivo' => $device
                instanceof AccessDeviceRecord
                    ? self::safeText(
                        (string) $device->display_name
                    )
                    : null,

            'operação' => self::operationLabel(
                $synchronization->operation
            ),

            'versão' => max(
                1,
                (int) $synchronization->version
            ),
        ]);
    }

    /**
     * @return array<string, int|string|null>
     */
    public static function failureProperties(
        ?FacialCredentialSynchronizationRecord $synchronization,
    ): array {
        if (
            ! $synchronization
                instanceof FacialCredentialSynchronizationRecord
        ) {
            return [
                'resultado' => 'Falha interna antes da conclusão da tentativa',
            ];
        }

        $device = self::device(
            $synchronization
        );

        return self::withoutEmptyValues([
            'resultado' => 'Falha interna antes da conclusão da tentativa',

            'dispositivo' => $device
                instanceof AccessDeviceRecord
                    ? self::safeText(
                        (string) $device->display_name
                    )
                    : null,

            'operação' => self::operationLabel(
                $synchronization->operation
            ),

            'versão' => max(
                1,
                (int) $synchronization->version
            ),
        ]);
    }

    private static function device(
        FacialCredentialSynchronizationRecord $synchronization
    ): ?AccessDeviceRecord {
        if (
            ! $synchronization->relationLoaded(
                'accessDevice'
            )
            && $synchronization->exists
        ) {
            $synchronization->loadMissing(
                'accessDevice'
            );
        }

        $device = $synchronization->getRelation(
            'accessDevice'
        );

        return $device instanceof AccessDeviceRecord
            ? $device
            : null;
    }

    private static function operationLabel(
        mixed $operation
    ): string {
        $value = $operation instanceof BackedEnum
            ? (string) $operation->value
            : (string) $operation;

        return match (
            strtolower(trim($value))
        ) {
            'register' => 'Cadastro',
            'replace' => 'Substituição',
            default => 'Operação facial',
        };
    }

    private static function providerLabel(
        ?string $provider
    ): ?string {
        return match (
            strtolower(trim((string) $provider))
        ) {
            'simulator' => 'Simulador local',
            'disabled' => 'Execução desativada',
            'vanguard' => 'VANGUARD',
            default => null,
        };
    }

    private static function scenarioLabel(
        ?string $scenario
    ): ?string {
        return match (
            strtolower(trim((string) $scenario))
        ) {
            'succeeded' => 'Sucesso sintético',

            'duplicate_photo' => 'Foto duplicada sintética',

            'failed' => 'Falha sintética',

            'invalid_response' => 'Resposta sintética inválida',

            default => null,
        };
    }

    private static function safeText(
        string $value
    ): ?string {
        $value = preg_replace(
            '/[\x00-\x1F\x7F]+/u',
            ' ',
            trim($value)
        );

        if (
            ! is_string($value)
            || $value === ''
        ) {
            return null;
        }

        return mb_strimwidth(
            $value,
            0,
            160,
            '…'
        );
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private static function withoutEmptyValues(
        array $properties
    ): array {
        return array_filter(
            $properties,
            static fn (mixed $value): bool => $value !== null
                && $value !== ''
        );
    }

    private static function safely(
        Closure $callback
    ): void {
        try {
            $callback();
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
