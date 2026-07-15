<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Decide;

interface DecideAccessEventRepository
{
    public function decide(
        string $eventId,
        bool $automaticExecutionAllowed,
    ): ?DecideAccessEventResult;
}
