<?php

namespace App\Modules\Operations\Application\Visits\AuthorizeVisit;

use App\Modules\Operations\Domain\Visits\VisitAuthorizationMethod;
use App\Support\Contracts\Command;
use DateTimeInterface;

final readonly class AuthorizeVisitCommand implements Command
{
    public function __construct(
        public string $visitId,
        public string $authorizerEmployeeId,
        public int $recordedByUserId,
        public VisitAuthorizationMethod $method,
        public ?string $notes = null,
        public ?DateTimeInterface $authorizedAt = null,
    ) {}
}
