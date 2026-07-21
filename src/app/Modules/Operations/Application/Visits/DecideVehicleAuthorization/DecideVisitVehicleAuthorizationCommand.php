<?php

namespace App\Modules\Operations\Application\Visits\DecideVehicleAuthorization;

use App\Modules\Operations\Domain\Visits\VisitVehicleAuthorizationStatus;
use App\Support\Contracts\Command;
use DateTimeInterface;

final readonly class DecideVisitVehicleAuthorizationCommand implements Command
{
    public function __construct(
        public string $requestId,
        public string $tenantId,
        public string $organizationId,
        public int $decidedByUserId,
        public VisitVehicleAuthorizationStatus $decision,
        public ?string $notes = null,
        public ?DateTimeInterface $decidedAt = null,
    ) {}
}
