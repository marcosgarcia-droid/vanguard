<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use InvalidArgumentException;

final readonly class AccessDeviceConnectionData
{
    public function __construct(
        public string $deviceId,
        public string $protocol,
        public string $ipAddress,
        public int $port,
        public string $username,
        public string $password,
        public bool $verifyTls = false,
    ) {
        if (! in_array($this->protocol, ['http', 'https'], true)) {
            throw new InvalidArgumentException(
                'O protocolo deve ser HTTP ou HTTPS.'
            );
        }

        if (! filter_var($this->ipAddress, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException(
                'O endereço IP do dispositivo é inválido.'
            );
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw new InvalidArgumentException(
                'A porta do dispositivo é inválida.'
            );
        }

        if (blank($this->username) || blank($this->password)) {
            throw new InvalidArgumentException(
                'As credenciais do dispositivo são obrigatórias.'
            );
        }
    }

    public function baseUrl(): string
    {
        return "{$this->protocol}://{$this->ipAddress}:{$this->port}";
    }
}
