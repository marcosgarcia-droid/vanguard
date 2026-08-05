<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Execute;

enum ExecuteFacialCredentialSynchronizationReason: string
{
    case Executed = 'executed';
    case Reused = 'reused';

    case SynchronizationNotFound =
        'synchronization_not_found';

    case SynchronizationSuperseded =
        'synchronization_superseded';

    case SynchronizationNotExecutable =
        'synchronization_not_executable';

    case InconsistentState =
        'inconsistent_state';

    public function isSuccessful(): bool
    {
        return in_array(
            $this,
            [
                self::Executed,
                self::Reused,
            ],
            true
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Executed => 'Tentativa registrada',

            self::Reused => 'Resultado existente reutilizado',

            self::SynchronizationNotFound => 'Intenção não localizada',

            self::SynchronizationSuperseded => 'Intenção substituída por uma versão mais recente',

            self::SynchronizationNotExecutable => 'Intenção não está disponível para execução',

            self::InconsistentState => 'Estado inconsistente da intenção',
        };
    }
}
