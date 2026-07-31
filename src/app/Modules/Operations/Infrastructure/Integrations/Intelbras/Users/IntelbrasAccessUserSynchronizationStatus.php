<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users;

enum IntelbrasAccessUserSynchronizationStatus: string
{
    case Blocked = 'blocked';

    case Simulated = 'simulated';

    public function label(): string
    {
        return match ($this) {
            self::Blocked => 'Bloqueada',
            self::Simulated => 'Simulada',
        };
    }
}
