<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain\FacialCredentials;

enum FacialCredentialSynchronizationAttemptStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Blocked = 'blocked';
    case RequiresAttention = 'requires_attention';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Processing => 'Em processamento',
            self::Succeeded => 'Concluída',
            self::Failed => 'Falhou',
            self::Blocked => 'Bloqueada',
            self::RequiresAttention => 'Requer atenção',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded,
            self::Failed,
            self::Blocked,
            self::RequiresAttention => true,

            self::Pending,
            self::Processing => false,
        };
    }
}
