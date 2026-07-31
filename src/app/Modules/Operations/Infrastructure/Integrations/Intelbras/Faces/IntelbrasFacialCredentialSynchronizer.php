<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

interface IntelbrasFacialCredentialSynchronizer
{
    public function synchronize(
        IntelbrasFacialCredentialRequest $request
    ): IntelbrasFacialCredentialSynchronizationResult;
}
