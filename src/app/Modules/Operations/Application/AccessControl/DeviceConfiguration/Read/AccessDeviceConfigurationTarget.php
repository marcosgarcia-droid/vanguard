<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;

final readonly class AccessDeviceConfigurationTarget
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public string $deviceId,
        public string $provider,
        public AccessDeviceStatus $status,
        public ?string $model,
        public ?string $protocol,
        public ?string $ipAddress,
        public ?int $port,
        public ?string $authType,
        public ?string $username,
        public ?string $password,
        public bool $verifyTls,
        public array $settings = [],
    ) {}
}
