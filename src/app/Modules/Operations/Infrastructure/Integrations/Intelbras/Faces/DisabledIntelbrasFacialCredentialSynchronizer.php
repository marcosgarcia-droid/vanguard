<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

final class DisabledIntelbrasFacialCredentialSynchronizer implements IntelbrasFacialCredentialSynchronizer
{
    public function synchronize(
        IntelbrasFacialCredentialRequest $request
    ): IntelbrasFacialCredentialSynchronizationResult {
        return IntelbrasFacialCredentialSynchronizationResult::blocked(
            $request
        );
    }
}
