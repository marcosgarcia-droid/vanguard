<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Reprocess;

use App\Support\Contracts\Command;

final readonly class ReprocessAccessEventFlowCommand implements Command
{
    public function __construct(
        public string $eventId,
        public int $operatorUserId,
        public string $idempotencyKey,
    ) {}
}
