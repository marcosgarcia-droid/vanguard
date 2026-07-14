<?php

namespace App\Modules\Operations\Application\Visits\CheckOutVisit;

use App\Support\Contracts\Command;
use DateTimeInterface;

final readonly class CheckOutVisitCommand implements Command
{
    public function __construct(
        public string $visitId,
        public int $operatorUserId,
        public ?DateTimeInterface $checkedOutAt = null,
    ) {}
}
