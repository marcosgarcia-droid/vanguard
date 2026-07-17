<?php

namespace App\Modules\Operations\Domain\AccessControl;

enum AccessEventManualReviewDisposition: string
{
    case PendingCorrection = 'pending_correction';
    case ReadyForReprocessing = 'ready_for_reprocessing';
    case ResolvedWithoutOperation = 'resolved_without_operation';

    public function label(): string
    {
        return match ($this) {
            self::PendingCorrection => 'Aguardando correção',
            self::ReadyForReprocessing => 'Pronto para reprocessamento',
            self::ResolvedWithoutOperation => 'Resolvido sem operação',
        };
    }

    public function isResolved(): bool
    {
        return $this === self::ResolvedWithoutOperation;
    }

    public function requestsReprocessing(): bool
    {
        return $this === self::ReadyForReprocessing;
    }
}
