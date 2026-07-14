<?php

namespace App\Modules\Operations\Application\Visits\CheckInVisit;

use App\Support\Contracts\Command;
use DateTimeInterface;

final readonly class CheckInVisitCommand implements Command
{
    public function __construct(
        public string $visitId,
        public int $operatorUserId,
        public ?DateTimeInterface $checkedInAt = null,
    ) {}
}
