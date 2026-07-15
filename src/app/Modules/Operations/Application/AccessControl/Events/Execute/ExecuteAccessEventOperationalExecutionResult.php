<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Execute;

use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;

final readonly class ExecuteAccessEventOperationalExecutionResult
{
    public function __construct(
        public string $executionId,
        public string $decisionId,
        public string $eventId,
        public ?string $visitId,
        public AccessEventOperationalDecision $decision,
        public AccessEventOperationalExecutionStatus $status,
        public string $reasonCode,
        public ?VisitStatus $visitStatusBefore,
        public ?VisitStatus $visitStatusAfter,
        public bool $duplicate,
    ) {}
}
