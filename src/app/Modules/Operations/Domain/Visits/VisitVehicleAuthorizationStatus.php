<?php

namespace App\Modules\Operations\Domain\Visits;

enum VisitVehicleAuthorizationStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando autorização',
            self::Authorized => 'Entrada autorizada',
            self::Rejected => 'Entrada recusada',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::Authorized,
            self::Rejected,
        ], true);
    }
}
