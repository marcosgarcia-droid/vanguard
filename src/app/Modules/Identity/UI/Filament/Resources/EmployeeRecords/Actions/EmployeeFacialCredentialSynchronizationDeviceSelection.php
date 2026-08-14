<?php

declare(strict_types=1);

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Actions;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Illuminate\Database\Eloquent\Builder;

final class EmployeeFacialCredentialSynchronizationDeviceSelection
{
    /**
     * @return array<string, string>
     */
    public static function options(
        EmployeeRecord $employee
    ): array {
        return self::query($employee)
            ->get([
                'id',
                'code',
                'name',
                'model',
            ])
            ->mapWithKeys(
                static fn (
                    AccessDeviceRecord $device
                ): array => [
                    (string) $device->getKey() => self::label($device),
                ]
            )
            ->all();
    }

    public static function isSelectable(
        EmployeeRecord $employee,
        mixed $deviceId,
    ): bool {
        if (
            ! is_string($deviceId)
            || trim($deviceId) === ''
        ) {
            return false;
        }

        return self::query($employee)
            ->whereKey(
                trim($deviceId)
            )
            ->exists();
    }

    public static function unavailableReason(
        EmployeeRecord $employee
    ): string {
        if (
            blank($employee->tenant_id)
            || blank($employee->organization_id)
        ) {
            return 'O funcionário não possui grupo empresarial e unidade válidos.';
        }

        if (self::options($employee) === []) {
            return 'Nenhum leitor facial ativo desta unidade possui uma leitura válida de modelo e firmware.';
        }

        return 'Selecione o leitor facial que deverá receber a intenção de sincronização.';
    }

    private static function query(
        EmployeeRecord $employee
    ): Builder {
        if (
            blank($employee->tenant_id)
            || blank($employee->organization_id)
        ) {
            return AccessDeviceRecord::query()
                ->whereRaw('1 = 0');
        }

        return AccessDeviceRecord::query()
            ->where(
                'tenant_id',
                $employee->tenant_id
            )
            ->where(
                'organization_id',
                $employee->organization_id
            )
            ->where(
                'status',
                AccessDeviceStatus::Active->value
            )
            ->whereRaw(
                'LOWER(TRIM(provider)) = ?',
                ['intelbras']
            )
            ->whereRaw(
                'LOWER(TRIM(device_type)) = ?',
                ['facial_reader']
            )
            ->whereHas(
                'configurationSnapshots',
                static function (
                    Builder $query
                ) use ($employee): void {
                    $query
                        ->where(
                            'tenant_id',
                            $employee->tenant_id
                        )
                        ->where(
                            'organization_id',
                            $employee->organization_id
                        )
                        ->where(
                            'status',
                            AccessDeviceConfigurationReadStatus::Success
                                ->value
                        )
                        ->whereNotNull(
                            'device_model'
                        )
                        ->whereNotNull(
                            'firmware_version'
                        )
                        ->where(
                            'device_model',
                            '<>',
                            ''
                        )
                        ->where(
                            'firmware_version',
                            '<>',
                            ''
                        );
                }
            )
            ->orderBy('code')
            ->orderBy('name')
            ->orderBy('id');
    }

    private static function label(
        AccessDeviceRecord $device
    ): string {
        $displayName = trim(
            (string) $device->display_name
        );

        $model = trim(
            (string) $device->model
        );

        if ($displayName === '') {
            $displayName =
                'Leitor facial sem identificação';
        }

        return $model !== ''
            ? $displayName.' — '.$model
            : $displayName;
    }
}
