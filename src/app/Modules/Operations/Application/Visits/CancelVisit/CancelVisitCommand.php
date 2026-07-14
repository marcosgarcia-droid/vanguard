<?php

namespace App\Modules\Operations\Application\Visits\CancelVisit;

use App\Support\Contracts\Command;
use DateTimeInterface;

final readonly class CancelVisitCommand implements Command
{
    public function __construct(
        public string $visitId,
        public int $operatorUserId,
        public ?string $reason = null,
        public ?DateTimeInterface $cancelledAt = null,
    ) {}
}
