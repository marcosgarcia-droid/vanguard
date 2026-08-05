<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Execute;

final readonly class ExecuteFacialCredentialSynchronizationUseCase
{
    public function __construct(
        private ExecuteFacialCredentialSynchronizationRepository $repository
    ) {}

    public function execute(
        ExecuteFacialCredentialSynchronizationCommand $command
    ): ExecuteFacialCredentialSynchronizationResult {
        return $this->repository->execute(
            $command
        );
    }
}
