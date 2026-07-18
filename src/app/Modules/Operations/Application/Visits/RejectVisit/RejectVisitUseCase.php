<?php

namespace App\Modules\Operations\Application\Visits\RejectVisit;

use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Support\Contracts\TransactionManager;
use App\Support\Contracts\UseCase;

final readonly class RejectVisitUseCase implements UseCase
{
    public function __construct(
        private TransactionManager $transactions,
    ) {}

    public function execute(RejectVisitCommand $command): VisitRecord
    {
        return $this->transactions->run(function () use ($command): VisitRecord {
            $visit = VisitRecord::query()
                ->lockForUpdate()
                ->find($command->visitId);

            if (! $visit) {
                throw VisitOperationException::visitNotFound();
            }

            if (
                $visit->status === VisitStatus::Rejected
                && $visit->rejected_at !== null
            ) {
                return $visit;
            }

            if (! $visit->status->canReject()) {
                throw VisitOperationException::invalidStatus(
                    operation: 'recusar a visita',
                    status: $visit->status,
                );
            }

            $reason = filled($command->reason)
                ? trim((string) $command->reason)
                : null;

            $visit->fill([
                'status' => VisitStatus::Rejected,
                'rejected_by' => $command->operatorUserId,
                'rejected_at' => $command->rejectedAt ?? now(),
                'rejection_reason' => $reason,
                'authorizer_employee_id' => null,
                'authorization_method' => null,
                'authorization_notes' => null,
                'authorized_by' => null,
                'authorized_at' => null,
            ]);

            $visit->save();
            $visit->refresh();

            return $visit;
        });
    }
}
