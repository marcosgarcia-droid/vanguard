<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Decide;

use App\Support\Contracts\Command;

final readonly class DecideAccessEventCommand implements Command
{
    public function __construct(
        public string $eventId,
    ) {}
}
