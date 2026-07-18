<?php

namespace App\Modules\Operations\Application\Visits\RejectVisit;

use App\Support\Contracts\Command;
use DateTimeInterface;

final readonly class RejectVisitCommand implements Command
{
    public function __construct(
        public string $visitId,
        public int $operatorUserId,
        public ?string $reason = null,
        public ?DateTimeInterface $rejectedAt = null,
    ) {}
}
