<?php

namespace App\Modules\Operations\Application\AccessControl\Events\ManualAssociate;

interface ManualAssociateAccessEventRepository
{
    public function associate(
        string $eventId,
        string $visitorId,
        ?string $visitId,
        int $operatorUserId,
        string $reason,
        string $idempotencyKey,
    ): ?ManualAssociateAccessEventResult;
}
