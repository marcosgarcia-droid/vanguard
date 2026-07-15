<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

interface AccessDeviceConfigurationReadLease
{
    /**
     * Registra que o reader será efetivamente chamado.
     */
    public function markReaderCalled(): void;

    /**
     * Libera a exclusividade da leitura.
     */
    public function release(): void;
}
