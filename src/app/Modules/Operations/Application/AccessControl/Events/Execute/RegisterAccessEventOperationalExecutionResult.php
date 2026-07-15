<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Execute;

use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionSource;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;

final readonly class RegisterAccessEventOperationalExecutionResult
{
    public function __construct(
        public string $executionId,
        public string $decisionId,
        public string $eventId,
        public int $attemptNumber,
        public AccessEventOperationalExecutionSource $source,
        public AccessEventOperationalExecutionStatus $status,
        public string $reasonCode,
        public bool $automaticExecutionAllowed,
        public bool $duplicate,
    ) {}
}
