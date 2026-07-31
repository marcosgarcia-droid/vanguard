<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users;

interface IntelbrasAccessUserSynchronizer
{
    public function synchronize(
        IntelbrasAccessUserPayload $payload
    ): IntelbrasAccessUserSynchronizationResult;
}
