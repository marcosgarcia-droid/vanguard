<?php

namespace App\Modules\Operations\Application\Visits\RequestVehicleAuthorization;

use App\Support\Contracts\Command;
use DateTimeInterface;

final readonly class RequestVisitVehicleAuthorizationCommand implements Command
{
    public function __construct(
        public int $visitVehicleId,
        public string $tenantId,
        public string $organizationId,
        public int $requestedByUserId,
        public string $idempotencyKey,
        public ?string $notes = null,
        public ?DateTimeInterface $requestedAt = null,
    ) {}
}
