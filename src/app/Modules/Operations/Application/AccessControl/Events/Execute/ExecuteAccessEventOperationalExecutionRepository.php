<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Execute;

interface ExecuteAccessEventOperationalExecutionRepository
{
    public function executeAutomaticAttempt(
        string $executionId,
        bool $automaticExecutionAllowed,
    ): ?ExecuteAccessEventOperationalExecutionResult;

    public function markFailed(
        string $executionId,
    ): void;
}
