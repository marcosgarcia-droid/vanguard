<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;

final readonly class ReadAccessDeviceConfigurationResult
{
    /**
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public string $snapshotId,
        public AccessDeviceConfigurationReadStatus $status,
        public string $message,
        public array $warnings,
    ) {}

    public function isPartial(): bool
    {
        return $this->status
            === AccessDeviceConfigurationReadStatus::Partial;
    }
}
