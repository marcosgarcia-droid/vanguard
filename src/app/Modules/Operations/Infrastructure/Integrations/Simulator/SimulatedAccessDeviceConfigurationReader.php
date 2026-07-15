<?php

namespace App\Modules\Operations\Infrastructure\Integrations\Simulator;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReader;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadException;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadResult;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConnectionData;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceCapabilityStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;

final readonly class SimulatedAccessDeviceConfigurationReader implements AccessDeviceConfigurationReader
{
    public function read(
        AccessDeviceConnectionData $connection
    ): AccessDeviceConfigurationReadResult {
        if (
            ! (bool) config(
                'access_control.simulator_enabled',
                false
            )
        ) {
            throw new AccessDeviceConfigurationReadException(
                'O simulador de dispositivos está desativado neste ambiente.'
            );
        }

        $scenario = strtolower(
            trim(
                (string) data_get(
                    $connection->metadata,
                    'scenario',
                    config(
                        'access_control.simulator_default_scenario',
                        'success'
                    )
                )
            )
        );

        return match ($scenario) {
            'success' => $this->success($connection),
            'partial' => $this->partial($connection),
            'failed' => throw new AccessDeviceConfigurationReadException(
                'Falha sintética de comunicação com o dispositivo simulado.',
                'simulator'
            ),
            default => throw new AccessDeviceConfigurationReadException(
                'O cenário configurado para o simulador é inválido.',
                'simulator'
            ),
        };
    }

    private function success(
        AccessDeviceConnectionData $connection
    ): AccessDeviceConfigurationReadResult {
        $supported =
            AccessDeviceCapabilityStatus::Supported->value;

        return new AccessDeviceConfigurationReadResult(
            status: AccessDeviceConfigurationReadStatus::Success,
            configuration: [
                'device' => [
                    'provider' => 'simulator',
                    'date_time' => '2026-01-15 08:00:00',
                    'firmware_version' => 'SIMULATOR-1.0.0',
                ],
                'turnstile' => [
                    'direction' => 'entry',
                ],
                'door' => [
                    'current_status' => 'Close',
                    'exit_button_enabled' => true,
                    'sensor_enabled' => true,
                    'relay_activation_seconds' => 3,
                ],
                'alarms' => [
                    'break_in_enabled' => true,
                    'door_open_enabled' => true,
                    'duress_enabled' => false,
                ],
            ],
            capabilities: [
                'device' => [
                    'date_time' => $supported,
                    'firmware_version' => $supported,
                ],
                'turnstile' => [
                    'direction' => $supported,
                ],
                'door' => [
                    'current_status' => $supported,
                    'exit_button_enabled' => $supported,
                    'sensor_enabled' => $supported,
                    'relay_activation_seconds' => $supported,
                ],
                'alarms' => [
                    'break_in_enabled' => $supported,
                    'door_open_enabled' => $supported,
                    'duress_enabled' => $supported,
                ],
            ],
            sanitizedResponse: [
                'simulator' => [
                    'scenario' => 'success',
                    'fixture' => 'configuration-v1',
                    'device_fingerprint' => $this->fingerprint(
                        $connection->deviceId
                    ),
                ],
            ],
            firmwareVersion: 'SIMULATOR-1.0.0',
            durationMs: 5,
            message: 'Leitura sintética concluída com sucesso.',
        );
    }

    private function partial(
        AccessDeviceConnectionData $connection
    ): AccessDeviceConfigurationReadResult {
        $supported =
            AccessDeviceCapabilityStatus::Supported->value;

        $warning =
            'Versão do firmware não disponibilizada pelo cenário sintético.';

        return new AccessDeviceConfigurationReadResult(
            status: AccessDeviceConfigurationReadStatus::Partial,
            configuration: [
                'device' => [
                    'provider' => 'simulator',
                    'date_time' => '2026-01-15 08:00:00',
                ],
                'door' => [
                    'current_status' => 'Close',
                ],
            ],
            capabilities: [
                'device' => [
                    'date_time' => $supported,
                ],
                'door' => [
                    'current_status' => $supported,
                ],
            ],
            sanitizedResponse: [
                'simulator' => [
                    'scenario' => 'partial',
                    'fixture' => 'configuration-v1',
                    'device_fingerprint' => $this->fingerprint(
                        $connection->deviceId
                    ),
                ],
            ],
            firmwareVersion: null,
            durationMs: 5,
            message: 'Leitura sintética concluída parcialmente.',
            warnings: [$warning],
        );
    }

    private function fingerprint(
        string $deviceId
    ): string {
        return substr(
            hash('sha256', $deviceId),
            0,
            12
        );
    }
}
