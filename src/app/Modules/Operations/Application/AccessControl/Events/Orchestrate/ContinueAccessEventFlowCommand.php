<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Orchestrate;

use App\Support\Contracts\Command;

final readonly class ContinueAccessEventFlowCommand implements Command
{
    public function __construct(
        public string $eventId,
    ) {}
}
