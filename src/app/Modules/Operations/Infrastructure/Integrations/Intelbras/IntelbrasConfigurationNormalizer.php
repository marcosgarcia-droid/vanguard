<?php

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras;

use App\Modules\Operations\Domain\AccessControl\AccessDeviceCapabilityStatus;
use Illuminate\Support\Arr;

final class IntelbrasConfigurationNormalizer
{
    /**
     * @param  array<string, array<string, mixed>>  $responses
     * @return array{
     *     configuration: array<string, mixed>,
     *     capabilities: array<string, mixed>,
     *     firmware_version: string|null
     * }
     */
    public function normalize(array $responses): array
    {
        $configuration = [];
        $capabilities = [];

        $this->mapValue(
            $responses,
            IntelbrasReadOnlyEndpoint::CurrentTime,
            'result',
            $configuration,
            $capabilities,
            'device.date_time'
        );

        $this->mapValue(
            $responses,
            IntelbrasReadOnlyEndpoint::SoftwareVersion,
            'version',
            $configuration,
            $capabilities,
            'device.firmware_version'
        );

        $this->mapValue(
            $responses,
            IntelbrasReadOnlyEndpoint::DoorStatus,
            'Info.status',
            $configuration,
            $capabilities,
            'door.current_status'
        );

        $this->mapValue(
            $responses,
            IntelbrasReadOnlyEndpoint::AccessControlGeneral,
            'table.AccessControlGeneral.AccessProperty',
            $configuration,
            $capabilities,
            'turnstile.direction'
        );

        $this->mapValue(
            $responses,
            IntelbrasReadOnlyEndpoint::AccessControlGeneral,
            'table.AccessControlGeneral.ButtonExitEnable',
            $configuration,
            $capabilities,
            'door.exit_button_enabled'
        );

        $this->mapValue(
            $responses,
            IntelbrasReadOnlyEndpoint::AccessControlGeneral,
            'table.AccessControlGeneral.SensorType',
            $configuration,
            $capabilities,
            'door.sensor_state'
        );

        $this->mapValue(
            $responses,
            IntelbrasReadOnlyEndpoint::AccessControlGeneral,
            'table.AccessControlGeneral.OpenDoorByCardEnable',
            $configuration,
            $capabilities,
            'cards.reader_configuration'
        );

        $this->mapValue(
            $responses,
            IntelbrasReadOnlyEndpoint::AccessControl,
            'table.AccessControl[0].BreakInAlarmEnable',
            $configuration,
            $capabilities,
            'alarms.break_in_enabled'
        );

        $this->mapValue(
            $responses,
            IntelbrasReadOnlyEndpoint::AccessControl,
            'table.AccessControl[0].DoorNotClosedAlarmEnable',
            $configuration,
            $capabilities,
            'alarms.door_open_enabled'
        );

        $this->mapValue(
            $responses,
            IntelbrasReadOnlyEndpoint::AccessControl,
            'table.AccessControl[0].DuressAlarmEnable',
            $configuration,
            $capabilities,
            'alarms.duress_enabled'
        );

        $this->mapValue(
            $responses,
            IntelbrasReadOnlyEndpoint::AccessControl,
            'table.AccessControl[0].SensorEnable',
            $configuration,
            $capabilities,
            'door.sensor_enabled'
        );

        $this->mapValue(
            $responses,
            IntelbrasReadOnlyEndpoint::AccessControl,
            'table.AccessControl[0].CloseTimeout',
            $configuration,
            $capabilities,
            'door.sensor_delay_seconds'
        );

        $this->mapValue(
            $responses,
            IntelbrasReadOnlyEndpoint::AccessControl,
            'table.AccessControl[0].Method',
            $configuration,
            $capabilities,
            'door.verification_method'
        );

        $this->mapValue(
            $responses,
            IntelbrasReadOnlyEndpoint::AccessControl,
            'table.AccessControl[0].UnlockHoldInterval',
            $configuration,
            $capabilities,
            'door.relay_activation_seconds',
            fn (mixed $value): mixed => is_numeric($value)
                ? ((float) $value / 1000)
                : $value
        );

        return [
            'configuration' => $configuration,
            'capabilities' => $capabilities,
            'firmware_version' => $this->stringValue(
                $responses,
                IntelbrasReadOnlyEndpoint::SoftwareVersion,
                'version'
            ),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $responses
     * @param  array<string, mixed>  $configuration
     * @param  array<string, mixed>  $capabilities
     */
    private function mapValue(
        array $responses,
        IntelbrasReadOnlyEndpoint $endpoint,
        string $sourceKey,
        array &$configuration,
        array &$capabilities,
        string $targetKey,
        ?callable $transform = null
    ): void {
        $endpointValues =
            $responses[$endpoint->value] ?? [];

        if (! array_key_exists($sourceKey, $endpointValues)) {
            return;
        }

        $value = $endpointValues[$sourceKey];

        if ($transform !== null) {
            $value = $transform($value);
        }

        Arr::set(
            $configuration,
            $targetKey,
            $value
        );

        Arr::set(
            $capabilities,
            $targetKey,
            AccessDeviceCapabilityStatus::Supported->value
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $responses
     */
    private function stringValue(
        array $responses,
        IntelbrasReadOnlyEndpoint $endpoint,
        string $key
    ): ?string {
        $value =
            $responses[$endpoint->value][$key]
            ?? null;

        return filled($value)
            ? (string) $value
            : null;
    }
}
