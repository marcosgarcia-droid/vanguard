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
            self::PendingAuthorization => 'Aguardando autorização',
            self::Authorized => 'Autorizada',
            self::Rejected => 'Não autorizada',
            self::InProgress => 'Em andamento',
            self::Completed => 'Concluída',
            self::Cancelled => 'Cancelada',
            self::Expired => 'Expirada',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Cancelled,
            self::Expired,
        ], true);
    }

    public function canRegisterArrival(): bool
    {
        return in_array($this, [
            self::Scheduled,
            self::PendingAuthorization,
            self::Authorized,
        ], true);
    }

    public function canAuthorize(): bool
    {
        return in_array($this, [
            self::Scheduled,
            self::PendingAuthorization,
            self::Rejected,
        ], true);
    }

    public function canCheckIn(): bool
    {
        return $this === self::Authorized;
    }

    public function canCheckOut(): bool
    {
        return $this === self::InProgress;
    }

    public function canCancel(): bool
    {
        return in_array($this, [
            self::Draft,
            self::Scheduled,
            self::PendingAuthorization,
            self::Authorized,
        ], true);
    }

    /**
     * Status priorizados na interface operacional.
     *
     * @return array<string, string>
     */
    public static function operationalOptions(): array
    {
        return collect([
            self::Scheduled,
            self::PendingAuthorization,
            self::Authorized,
            self::InProgress,
            self::Completed,
            self::Cancelled,
        ])
            ->mapWithKeys(fn (self $status): array => [
                $status->value => $status->label(),
            ])
            ->all();
    }

    /**
     * Todos os status, inclusive os estados internos.
     *
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
