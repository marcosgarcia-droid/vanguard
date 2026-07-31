<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

enum IntelbrasFacialCredentialSynchronizationStatus: string
{
    case Blocked = 'blocked';

    case Simulated = 'simulated';

    public function safeMessage(): string
    {
        return match ($this) {
            self::Blocked => 'A sincronização facial está bloqueada e nenhum transporte foi executado.',

            self::Simulated => 'A sincronização facial foi executada somente no simulador.',
        };
    }
}
