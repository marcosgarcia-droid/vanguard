<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Orchestrate;

use App\Modules\Operations\Application\AccessControl\Events\Decide\DecideAccessEventResult;
use App\Modules\Operations\Application\AccessControl\Events\Execute\ExecuteAccessEventOperationalExecutionResult;
use App\Modules\Operations\Application\AccessControl\Events\Execute\RegisterAccessEventOperationalExecutionResult;
use App\Modules\Operations\Application\AccessControl\Events\Process\ProcessAccessEventResult;

final readonly class ContinueAccessEventFlowResult
{
    public function __construct(
        public string $eventId,
        public ProcessAccessEventResult $processing,
        public DecideAccessEventResult $decision,
        public RegisterAccessEventOperationalExecutionResult $registration,
        public ?ExecuteAccessEventOperationalExecutionResult $execution,
    ) {}
}
