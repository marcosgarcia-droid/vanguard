<?php

namespace App\Modules\Operations\Application\Visits\CancelVisit;

use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Support\Contracts\TransactionManager;
use App\Support\Contracts\UseCase;

final readonly class CancelVisitUseCase implements UseCase
{
    public function __construct(
        private TransactionManager $transactions,
    ) {}

    public function execute(CancelVisitCommand $command): VisitRecord
    {
        return $this->transactions->run(function () use ($command): VisitRecord {
            $visit = VisitRecord::query()
                ->lockForUpdate()
                ->find($command->visitId);

            if (! $visit) {
                throw VisitOperationException::visitNotFound();
            }

            if ($visit->status === VisitStatus::Cancelled) {
                return $visit;
            }

            if (! $visit->status->canCancel()) {
                throw VisitOperationException::invalidStatus(
                    operation: 'cancelar',
                    status: $visit->status,
                );
            }

            $reason = filled($command->reason)
                ? trim((string) $command->reason)
                : null;

            $visit->fill([
                'status' => VisitStatus::Cancelled,
                'cancelled_by' => $command->operatorUserId,
                'cancelled_at' => $command->cancelledAt ?? now(),
                'cancellation_reason' => $reason,
            ]);

            $visit->save();
            $visit->refresh();

            return $visit;
        });
    }
}
