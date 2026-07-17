<?php

namespace App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation;

use App\Support\Contracts\Command;

final readonly class ContinueManuallyAssociatedAccessEventFlowCommand implements Command
{
    public function __construct(
        public string $eventId,
        public int $operatorUserId,
    ) {}
}
