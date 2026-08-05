<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Execute;

final readonly class ExecuteFacialCredentialSynchronizationCommand
{
    public function __construct(
        public string $synchronizationId,
    ) {}
}
