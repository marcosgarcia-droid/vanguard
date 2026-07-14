<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

interface AccessDeviceConfigurationReader
{
    public function read(
        AccessDeviceConnectionData $connection
    ): AccessDeviceConfigurationReadResult;
}
