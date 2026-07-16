<?php

namespace App\Modules\Operations\Application\AccessControl\Events\ManualAssociate;

use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;

final readonly class ManualAssociateAccessEventResult
{
    public function __construct(
        public string $eventId,
        public string $associationId,
        public AccessEventStatus $status,
        public string $visitorId,
        public ?string $visitId,
        public string $resultCode,
        public bool $duplicate,
    ) {}
}
