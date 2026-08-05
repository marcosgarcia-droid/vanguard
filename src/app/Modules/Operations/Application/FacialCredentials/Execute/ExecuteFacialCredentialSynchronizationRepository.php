<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Execute;

interface ExecuteFacialCredentialSynchronizationRepository
{
    public function execute(
        ExecuteFacialCredentialSynchronizationCommand $command
    ): ExecuteFacialCredentialSynchronizationResult;
}
