<?php

namespace App\Modules\Operations\Application\Visits\CheckOutVisit;

use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Support\Contracts\TransactionManager;
use App\Support\Contracts\UseCase;

final readonly class CheckOutVisitUseCase implements UseCase
{
    public function __construct(
        private TransactionManager $transactions,
    ) {}

    public function execute(CheckOutVisitCommand $command): VisitRecord
    {
        return $this->transactions->run(function () use ($command): VisitRecord {
            $visit = VisitRecord::query()
                ->lockForUpdate()
                ->find($command->visitId);

            if (! $visit) {
                throw VisitOperationException::visitNotFound();
            }

            if (
                $visit->status === VisitStatus::Completed
                && $visit->checked_out_at !== null
            ) {
                return $visit;
            }

            if (! $visit->status->canCheckOut()) {
                throw VisitOperationException::invalidStatus(
                    operation: 'registrar a saída',
                    status: $visit->status,
                );
            }

            $visit->fill([
                'status' => VisitStatus::Completed,
                'checked_out_by' => $command->operatorUserId,
                'checked_out_at' => $command->checkedOutAt ?? now(),
            ]);

            $visit->save();
            $visit->refresh();

            return $visit;
        });
    }
}
