<?php

namespace App\Modules\Operations\Application\Visits\RegisterVisitArrival;

use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Support\Contracts\TransactionManager;
use App\Support\Contracts\UseCase;

final readonly class RegisterVisitArrivalUseCase implements UseCase
{
    public function __construct(
        private TransactionManager $transactions,
    ) {}

    public function execute(
        RegisterVisitArrivalCommand $command
    ): VisitRecord {
        return $this->transactions->run(function () use ($command): VisitRecord {
            $visit = VisitRecord::query()
                ->lockForUpdate()
                ->find($command->visitId);

            if (! $visit) {
                throw VisitOperationException::visitNotFound();
            }

            if ($visit->arrived_at !== null) {
                return $visit;
            }

            if (! $visit->status->canRegisterArrival()) {
                throw VisitOperationException::invalidStatus(
                    operation: 'registrar a chegada',
                    status: $visit->status,
                );
            }

            $arrivedAt = $command->arrivedAt ?? now();

            $attributes = [
                'arrived_by' => $command->operatorUserId,
                'arrived_at' => $arrivedAt,
                'identity_verified_by' => $command->operatorUserId,
                'identity_verified_at' => $arrivedAt,
            ];

            if ($visit->status === VisitStatus::Scheduled) {
                $attributes['status'] = VisitStatus::PendingAuthorization;
            }

            $visit->fill($attributes);
            $visit->save();

            return $visit;
        });
    }
}
