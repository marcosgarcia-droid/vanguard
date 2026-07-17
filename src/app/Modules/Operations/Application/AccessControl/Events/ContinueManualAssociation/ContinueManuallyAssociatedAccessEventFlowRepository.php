<?php

namespace App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation;

interface ContinueManuallyAssociatedAccessEventFlowRepository
{
    public function prepare(
        string $eventId,
        int $operatorUserId,
    ): ?ContinueManuallyAssociatedAccessEventFlowContext;
}
