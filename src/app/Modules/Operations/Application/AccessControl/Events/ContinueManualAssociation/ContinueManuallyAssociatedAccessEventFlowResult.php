<?php

namespace App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation;

use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowResult;

final readonly class ContinueManuallyAssociatedAccessEventFlowResult
{
    public function __construct(
        public string $eventId,
        public string $associationId,
        public ContinueAccessEventFlowResult $flow,
    ) {}
}
