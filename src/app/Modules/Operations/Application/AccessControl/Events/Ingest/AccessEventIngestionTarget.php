<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Ingest;

use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;

final readonly class AccessEventIngestionTarget
{
    public function __construct(
        public string $deviceId,
        public string $tenantId,
        public string $organizationId,
        public string $provider,
        public AccessDeviceDirection $direction,
        public AccessDeviceStatus $status,
    ) {}
}
