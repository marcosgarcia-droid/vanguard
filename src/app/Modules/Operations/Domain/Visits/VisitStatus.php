<?php

namespace App\Modules\Operations\Domain\Visits;

enum VisitStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case PendingAuthorization = 'pending_authorization';
    case Authorized = 'authorized';
    case Rejected = 'rejected';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Scheduled => 'Agendada',
            self::PendingAuthorization => 'Pendente de autorização',
            self::Authorized => 'Autorizada',
            self::Rejected => 'Recusada',
            self::InProgress => 'Em andamento',
            self::Completed => 'Encerrada',
            self::Cancelled => 'Cancelada',
            self::Expired => 'Expirada',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Rejected,
            self::Cancelled,
            self::Expired,
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
