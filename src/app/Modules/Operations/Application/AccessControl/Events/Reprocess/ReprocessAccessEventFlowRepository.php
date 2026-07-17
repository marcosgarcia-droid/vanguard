<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Reprocess;

interface ReprocessAccessEventFlowRepository
{
    public function prepare(
        string $eventId,
        int $operatorUserId,
    ): ?ReprocessAccessEventFlowContext;
}
