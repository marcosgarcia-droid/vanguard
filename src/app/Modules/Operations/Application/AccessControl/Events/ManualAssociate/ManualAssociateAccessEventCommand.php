<?php

namespace App\Modules\Operations\Application\AccessControl\Events\ManualAssociate;

use App\Support\Contracts\Command;

final readonly class ManualAssociateAccessEventCommand implements Command
{
    public function __construct(
        public string $eventId,
        public string $visitorId,
        public ?string $visitId,
        public int $operatorUserId,
        public string $reason,
        public string $idempotencyKey,
    ) {}
}
