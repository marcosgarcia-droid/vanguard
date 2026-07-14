<?php

namespace App\Modules\Operations\Application\Visits\CheckInVisit;

use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Support\Contracts\TransactionManager;
use App\Support\Contracts\UseCase;

final readonly class CheckInVisitUseCase implements UseCase
{
    public function __construct(
        private TransactionManager $transactions,
    ) {}

    public function execute(CheckInVisitCommand $command): VisitRecord
    {
        return $this->transactions->run(function () use ($command): VisitRecord {
            $visit = VisitRecord::query()
                ->with('visitor')
                ->lockForUpdate()
                ->find($command->visitId);

            if (! $visit) {
                throw VisitOperationException::visitNotFound();
            }

            if (
                $visit->status === VisitStatus::InProgress
                && $visit->checked_in_at !== null
            ) {
                return $visit;
            }

            if (! $visit->status->canCheckIn()) {
                throw VisitOperationException::invalidStatus(
                    operation: 'registrar a entrada',
                    status: $visit->status,
                );
            }

            if (! $visit->visitor || blank($visit->visitor->photo_path)) {
                throw VisitOperationException::visitorPhotoRequired();
            }

            $checkedInAt = $command->checkedInAt ?? now();

            $attributes = [
                'status' => VisitStatus::InProgress,
                'checked_in_by' => $command->operatorUserId,
                'checked_in_at' => $checkedInAt,
            ];

            if ($visit->arrived_at === null) {
                $attributes['arrived_by'] = $command->operatorUserId;
                $attributes['arrived_at'] = $checkedInAt;
            }

            if ($visit->identity_verified_at === null) {
                $attributes['identity_verified_by'] = $command->operatorUserId;
                $attributes['identity_verified_at'] = $checkedInAt;
            }

            $visit->fill($attributes);
            $visit->save();
            $visit->refresh();

            return $visit;
        });
    }
}
