<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration;

use App\Modules\Operations\Application\AccessControl\AccessControlRuntime;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceCapabilityStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationOperation;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Illuminate\Support\HtmlString;

final class AccessDeviceConfigurationPresenter
{
    /**
     * @param  array{
     *     key: string,
     *     label: string,
     *     operation: AccessDeviceConfigurationOperation,
     *     description: string
     * }  $definition
     */
    public static function render(
        ?AccessDeviceRecord $device,
        array $definition
    ): HtmlString {
        $key = $definition['key'];
        $operation = $definition['operation'];

        $current = match ($operation) {
            AccessDeviceConfigurationOperation::Command => 'Ação operacional',
            default => self::formatValue(
                data_get(
                    $device?->current_configuration,
                    $key
                ),
                'Ainda não consultado'
            ),
        };

        $desired = match ($operation) {
            AccessDeviceConfigurationOperation::Configuration => self::formatValue(
                data_get(
                    $device?->desired_configuration,
                    $key
                ),
                'Não definido'
            ),
            default => 'Não se aplica',
        };

        $capabilityValue = data_get(
            $device?->capabilities,
            $key,
            AccessDeviceCapabilityStatus::Unknown->value
        );

        $capability =
            AccessDeviceCapabilityStatus::tryFrom(
                (string) $capabilityValue
            ) ?? AccessDeviceCapabilityStatus::Unknown;

        $control = self::controlDescription(
            $operation
        );

        return new HtmlString(
            '<div>'
            .'<div><strong>Tipo:</strong> '
            .e($operation->label())
            .'</div>'
            .'<div><strong>Atual:</strong> '
            .e($current)
            .'</div>'
            .'<div><strong>Desejado:</strong> '
            .e($desired)
            .'</div>'
            .'<div><strong>Suporte:</strong> '
            .e($capability->label())
            .'</div>'
            .'<div><strong>Controle:</strong> '
            .e($control)
            .'</div>'
            .'<div class="text-sm text-gray-500">'
            .e($definition['description'])
            .'</div>'
            .'</div>'
        );
    }

    private static function controlDescription(
        AccessDeviceConfigurationOperation $operation
    ): string {
        if (
            $operation
            === AccessDeviceConfigurationOperation::Status
        ) {
            return 'Somente consulta';
        }

        $runtime = app(AccessControlRuntime::class);

        if (! $runtime->allowsWrites()) {
            return 'Bloqueado — modo '
                .$runtime->mode()->label();
        }

        return 'Não implementado nesta etapa';
    }

    private static function formatValue(
        mixed $value,
        string $emptyValue
    ): string {
        if ($value === null || $value === '') {
            return $emptyValue;
        }

        if (is_bool($value)) {
            return $value ? 'Ativado' : 'Desativado';
        }

        if (is_array($value)) {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ) ?: $emptyValue;
        }

        return (string) $value;
    }
}
