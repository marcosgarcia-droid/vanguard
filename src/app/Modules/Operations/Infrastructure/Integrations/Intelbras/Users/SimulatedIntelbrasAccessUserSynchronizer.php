<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users;

final readonly class SimulatedIntelbrasAccessUserSynchronizer implements IntelbrasAccessUserSynchronizer
{
    public function synchronize(
        IntelbrasAccessUserPayload $payload
    ): IntelbrasAccessUserSynchronizationResult {
        return IntelbrasAccessUserSynchronizationResult::simulated(
            externalUserId: $payload->externalUserId,
            payloadFingerprint: hash(
                'sha256',
                $payload->toDeterministicJson()
            ),
        );
    }
}
