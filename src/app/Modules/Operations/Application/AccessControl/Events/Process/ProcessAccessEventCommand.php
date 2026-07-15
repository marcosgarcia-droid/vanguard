<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Process;

use App\Support\Contracts\Command;

final readonly class ProcessAccessEventCommand implements Command
{
    public function __construct(
        public string $eventId,
    ) {}
}
