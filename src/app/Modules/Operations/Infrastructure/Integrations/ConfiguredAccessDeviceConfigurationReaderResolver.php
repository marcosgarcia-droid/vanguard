<?php

namespace App\Modules\Operations\Infrastructure\Integrations;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReader;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReaderResolver;
use App\Modules\Operations\Infrastructure\Integrations\Simulator\SimulatedAccessDeviceConfigurationReader;
use InvalidArgumentException;

final readonly class ConfiguredAccessDeviceConfigurationReaderResolver implements AccessDeviceConfigurationReaderResolver
{
    public function __construct(
        private AccessDeviceConfigurationReader $intelbrasReader,
        private SimulatedAccessDeviceConfigurationReader $simulatorReader,
    ) {}

    public function resolve(
        string $provider
    ): AccessDeviceConfigurationReader {
        return match (
            strtolower(trim($provider))
        ) {
            'intelbras' => $this->intelbrasReader,
            'simulator' => $this->simulatorReader,
            default => throw new InvalidArgumentException(
                'O provider do dispositivo não possui um leitor de configurações registrado.'
            ),
        };
    }
}
