<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

interface AccessDeviceConfigurationReadGuard
{
    /**
     * Reserva a execução exclusiva de uma leitura para o dispositivo.
     *
     * @throws AccessDeviceConfigurationReadGuardException
     */
    public function acquire(
        string $deviceId
    ): AccessDeviceConfigurationReadLease;
}
