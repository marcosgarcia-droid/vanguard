<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

interface AccessDeviceConfigurationReaderResolver
{
    public function resolve(
        string $provider
    ): AccessDeviceConfigurationReader;
}
