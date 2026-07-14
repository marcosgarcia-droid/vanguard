<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

interface AccessDeviceConfigurationReadRepository
{
    public function findTarget(
        string $deviceId
    ): ?AccessDeviceConfigurationTarget;

    public function persist(
        AccessDeviceConfigurationReadPersistenceData $data
    ): string;
}
