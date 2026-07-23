<?php

namespace App\Modules\Operations\Application\Visits\AuthorizeVisit;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Support\Contracts\TransactionManager;
use App\Support\Contracts\UseCase;

final readonly class AuthorizeVisitUseCase implements UseCase
{
    public function __construct(
        private TransactionManager $transactions,
    ) {}

    public function execute(AuthorizeVisitCommand $command): VisitRecord
    {
        return $this->transactions->run(function () use ($command): VisitRecord {
            $visit = VisitRecord::query()
                ->lockForUpdate()
                ->find($command->visitId);

            if (! $visit) {
                throw VisitOperationException::visitNotFound();
            }

            if (
                $visit->status === VisitStatus::Authorized
                && $visit->authorized_at !== null
            ) {
                return $visit;
            }

            if (! $visit->status->canAuthorize()) {
                throw VisitOperationException::invalidStatus(
                    operation: 'autorizar',
                    status: $visit->status,
                );
            }

            $authorizerExists = EmployeeRecord::query()
                ->whereKey($command->authorizerEmployeeId)
                ->where('tenant_id', $visit->tenant_id)
                ->where('status', 'active')
                ->exists();

            if (! $authorizerExists) {
                throw VisitOperationException::authorizerUnavailable();
            }

            $notes = filled($command->notes)
                ? trim((string) $command->notes)
                : null;

            $visit->fill([
                'status' => VisitStatus::Authorized,
                'authorizer_employee_id' => $command->authorizerEmployeeId,
                'authorization_method' => $command->method,
                'authorization_notes' => $notes,
                'authorized_by' => $command->recordedByUserId,
                'authorized_at' => $command->authorizedAt ?? now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $visit->save();

            return $visit;
        });
    }
}
