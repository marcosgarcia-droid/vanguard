<?php

namespace App\Modules\Operations\Domain\AccessControl;

enum AccessEventOperationalExecutionStatus: string
{
    case Pending = 'pending';
    case Blocked = 'blocked';
    case Executed = 'executed';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Blocked => 'Bloqueada',
            self::Executed => 'Executada',
            self::Skipped => 'Ignorada',
            self::Failed => 'Falhou',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [
                $status->value => $status->label(),
            ])
            ->all();
    }
}
