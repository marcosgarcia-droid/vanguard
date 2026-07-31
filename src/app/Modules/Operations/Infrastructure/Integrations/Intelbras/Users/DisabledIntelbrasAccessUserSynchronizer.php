<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users;

final readonly class DisabledIntelbrasAccessUserSynchronizer implements IntelbrasAccessUserSynchronizer
{
    public function synchronize(
        IntelbrasAccessUserPayload $payload
    ): IntelbrasAccessUserSynchronizationResult {
        return IntelbrasAccessUserSynchronizationResult::blocked(
            $payload->externalUserId
        );
    }
}
