<?php

namespace App\Modules\Operations\Application\Visits\RegisterVisitArrival;

use App\Support\Contracts\Command;
use DateTimeInterface;

final readonly class RegisterVisitArrivalCommand implements Command
{
    public function __construct(
        public string $visitId,
        public int $operatorUserId,
        public ?DateTimeInterface $arrivedAt = null,
    ) {}
}
