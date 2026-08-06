<?php

declare(strict_types=1);

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions;

use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Database\Eloquent\Builder;

final class VisitorFacialCredentialSynchronizationDeviceSelection
{
    /**
     * @return array<string, string>
     */
    public static function options(
        VisitorRecord $visitor
    ): array {
        return self::query($visitor)
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
        VisitorRecord $visitor,
        mixed $deviceId,
    ): bool {
        if (
            ! is_string($deviceId)
            || trim($deviceId) === ''
        ) {
            return false;
        }

        return self::query($visitor)
            ->whereKey(
                trim($deviceId)
            )
            ->exists();
    }

    public static function unavailableReason(
        VisitorRecord $visitor
    ): string {
        if (
            blank($visitor->tenant_id)
            || blank($visitor->organization_id)
        ) {
            return 'O visitante não possui grupo empresarial e unidade válidos.';
        }

        if (self::options($visitor) === []) {
            return 'Nenhum leitor facial ativo desta unidade possui uma leitura válida de modelo e firmware.';
        }

        return 'Selecione o leitor facial que deverá receber a intenção de sincronização.';
    }

    private static function query(
        VisitorRecord $visitor
    ): Builder {
        if (
            blank($visitor->tenant_id)
            || blank($visitor->organization_id)
        ) {
            return AccessDeviceRecord::query()
                ->whereRaw('1 = 0');
        }

        return AccessDeviceRecord::query()
            ->where(
                'tenant_id',
                $visitor->tenant_id
            )
            ->where(
                'organization_id',
                $visitor->organization_id
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
                ) use ($visitor): void {
                    $query
                        ->where(
                            'tenant_id',
                            $visitor->tenant_id
                        )
                        ->where(
                            'organization_id',
                            $visitor->organization_id
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
