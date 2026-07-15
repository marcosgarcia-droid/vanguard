<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Process;

interface ProcessAccessEventRepository
{
    public function process(
        string $eventId
    ): ?ProcessAccessEventResult;

    public function markFailed(
        string $eventId,
        string $message
    ): void;
}
