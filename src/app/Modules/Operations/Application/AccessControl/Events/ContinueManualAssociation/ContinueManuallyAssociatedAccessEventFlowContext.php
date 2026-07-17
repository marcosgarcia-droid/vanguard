<?php

namespace App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation;

final readonly class ContinueManuallyAssociatedAccessEventFlowContext
{
    public function __construct(
        public string $eventId,
        public string $associationId,
        public string $visitorId,
        public string $visitId,
    ) {}
}
