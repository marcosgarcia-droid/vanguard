<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Decide;

use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;

final readonly class DecideAccessEventResult
{
    public function __construct(
        public string $decisionId,
        public string $eventId,
        public int $version,
        public AccessEventOperationalDecision $decision,
        public string $reasonCode,
        public bool $automaticExecutionEnabled,
        public bool $duplicate,
    ) {}
}
