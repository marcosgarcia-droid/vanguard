<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain\FacialCredentials;

enum FacialCredentialSynchronizationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Blocked = 'blocked';
    case RequiresAttention = 'requires_attention';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Processing => 'Em processamento',
            self::Succeeded => 'Sincronizada',
            self::Failed => 'Falhou',
            self::Blocked => 'Bloqueada',
            self::RequiresAttention => 'Requer atenção',
            self::Superseded => 'Substituída',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded,
            self::Failed,
            self::Blocked,
            self::RequiresAttention,
            self::Superseded => true,

            self::Pending,
            self::Processing => false,
        };
    }
}
