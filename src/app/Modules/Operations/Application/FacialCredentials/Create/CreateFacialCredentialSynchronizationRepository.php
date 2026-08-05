<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Create;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;

interface CreateFacialCredentialSynchronizationRepository
{
    public function prepare(
        CreateFacialCredentialSynchronizationCommand $command
    ): FacialCredentialSynchronizationPreparation;

    public function persist(
        FacialCredentialSynchronizationContext $context,
        IntelbrasFacialCredentialOperation $operation,
        string $planFingerprint,
        string $contextFingerprint,
    ): CreateFacialCredentialSynchronizationResult;
}
