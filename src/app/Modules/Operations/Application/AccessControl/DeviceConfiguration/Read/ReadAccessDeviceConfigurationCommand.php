<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationSource;

final readonly class ReadAccessDeviceConfigurationCommand
{
    public function __construct(
        public string $deviceId,
        public ?int $requestedByUserId = null,
        public AccessDeviceConfigurationSource $source =
            AccessDeviceConfigurationSource::Manual,
    ) {}
}
