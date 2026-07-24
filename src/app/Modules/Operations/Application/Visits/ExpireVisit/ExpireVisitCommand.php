<?php

namespace App\Modules\Operations\Application\Visits\ExpireVisit;

use App\Support\Contracts\Command;
use DateTimeInterface;

final readonly class ExpireVisitCommand implements Command
{
    public function __construct(
        public string $visitId,
        public DateTimeInterface $referenceAt,
        public int $graceHours,
    ) {}
}
