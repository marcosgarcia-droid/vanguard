<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Execute;

interface RegisterAccessEventOperationalExecutionRepository
{
    public function registerAutomaticAttempt(
        string $decisionId,
        bool $automaticExecutionAllowed,
    ): ?RegisterAccessEventOperationalExecutionResult;
}
