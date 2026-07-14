<?php

namespace App\Modules\Operations\Domain\AccessControl;

enum AccessEventStatus: string
{
    case Received = 'received';
    case PendingAssociation = 'pending_association';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Recebido',
            self::PendingAssociation => 'Aguardando associação',
            self::Processed => 'Processado',
            self::Ignored => 'Ignorado',
            self::Failed => 'Falha no processamento',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::Processed,
            self::Ignored,
        ], true);
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
