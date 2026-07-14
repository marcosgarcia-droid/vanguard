<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationSource;

final readonly class AccessDeviceConfigurationReadPersistenceData
{
    /**
     * @param  array<string, mixed>  $configuration
     * @param  array<string, mixed>  $capabilities
     * @param  array<string, mixed>  $sanitizedResponse
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public string $deviceId,
        public ?int $requestedByUserId,
        public AccessDeviceConfigurationSource $source,
        public AccessDeviceConfigurationReadStatus $status,
        public array $configuration,
        public array $capabilities,
        public array $sanitizedResponse,
        public ?string $firmwareVersion,
        public int $durationMs,
        public string $message,
        public array $warnings,
    ) {}
}
