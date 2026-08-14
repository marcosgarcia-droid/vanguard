<?php

declare(strict_types=1);

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Actions;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationResult;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Closure;
use Throwable;

final class EmployeeFacialCredentialSynchronizationCreationAudit
{
    public static function record(
        EmployeeRecord $employee,
        User $user,
        AccessDeviceRecord $device,
        IntelbrasFacialCredentialOperation $operation,
        CreateFacialCredentialSynchronizationResult $result,
    ): void {
        self::safely(
            static function () use (
                $employee,
                $user,
                $device,
                $operation,
                $result
            ): void {
                $event = match (true) {
                    $result->wasCreated() => 'employee_facial_credential_synchronization_created',

                    $result->wasReused() => 'employee_facial_credential_synchronization_reused',

                    default => 'employee_facial_credential_synchronization_blocked',
                };

                $description = match (true) {
                    $result->wasCreated() => 'Intenção de sincronização facial criada',

                    $result->wasReused() => 'Intenção de sincronização facial reutilizada',

                    default => 'Preparação da sincronização facial bloqueada',
                };

                activity('visitor_management')
                    ->causedBy($user)
                    ->performedOn($employee)
                    ->event($event)
                    ->withProperties([
                        'resultado' => $result->reason->label(),

                        'planejamento' => $result
                            ->planningReason
                            ?->label(),

                        'operação' => EmployeeFacialCredentialSynchronizationCreationPresentation::operationLabel(
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
        EmployeeRecord $employee,
        User $user,
        ?AccessDeviceRecord $device,
    ): void {
        self::safely(
            static function () use (
                $employee,
                $user,
                $device
            ): void {
                activity('visitor_management')
                    ->causedBy($user)
                    ->performedOn($employee)
                    ->event(
                        'employee_facial_credential_synchronization_failed'
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
