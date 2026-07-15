<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Process;

use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;

final readonly class ProcessAccessEventResult
{
    public function __construct(
        public string $eventId,
        public AccessEventStatus $status,
        public ?string $visitorId,
        public ?string $visitId,
        public string $resultCode,
        public int $processingAttempts,
        public bool $duplicate,
    ) {}
}
