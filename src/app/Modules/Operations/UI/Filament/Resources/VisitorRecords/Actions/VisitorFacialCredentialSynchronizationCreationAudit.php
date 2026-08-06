<?php

declare(strict_types=1);

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationResult;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Closure;
use Throwable;

final class VisitorFacialCredentialSynchronizationCreationAudit
{
    public static function record(
        VisitorRecord $visitor,
        User $user,
        AccessDeviceRecord $device,
        IntelbrasFacialCredentialOperation $operation,
        CreateFacialCredentialSynchronizationResult $result,
    ): void {
        self::safely(
            static function () use (
                $visitor,
                $user,
                $device,
                $operation,
                $result
            ): void {
                $event = match (true) {
                    $result->wasCreated() => 'visitor_facial_credential_synchronization_created',

                    $result->wasReused() => 'visitor_facial_credential_synchronization_reused',

                    default => 'visitor_facial_credential_synchronization_blocked',
                };

                $description = match (true) {
                    $result->wasCreated() => 'Intenção de sincronização facial criada',

                    $result->wasReused() => 'Intenção de sincronização facial reutilizada',

                    default => 'Preparação da sincronização facial bloqueada',
                };

                activity('visitor_management')
                    ->causedBy($user)
                    ->performedOn($visitor)
                    ->event($event)
                    ->withProperties([
                        'resultado' => $result->reason->label(),

                        'planejamento' => $result
                            ->planningReason
                            ?->label(),

                        'operação' => VisitorFacialCredentialSynchronizationCreationPresentation::operationLabel(
                            $operation
                        ),

                        'dispositivo' => self::safeText(
                            (string) $device->display_name
                        ),

                        'modelo' => self::safeText(
                            (string) $device->model
                        ),

                        'versão' => $result->version,
                    ])
                    ->log($description);
            }
        );
    }

    public static function failure(
        VisitorRecord $visitor,
        User $user,
        ?AccessDeviceRecord $device,
    ): void {
        self::safely(
            static function () use (
                $visitor,
                $user,
                $device
            ): void {
                activity('visitor_management')
                    ->causedBy($user)
                    ->performedOn($visitor)
                    ->event(
                        'visitor_facial_credential_synchronization_failed'
                    )
                    ->withProperties([
                        'resultado' => 'Falha interna ao preparar a intenção',

                        'dispositivo' => $device instanceof AccessDeviceRecord
                                ? self::safeText(
                                    (string) $device->display_name
                                )
                                : null,
                    ])
                    ->log(
                        'Falha ao preparar intenção de sincronização facial'
                    );
            }
        );
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
