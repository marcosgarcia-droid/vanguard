<?php

namespace App\Modules\Operations\Application\Visits\DecideVehicleAuthorization;

use App\Models\User;
use App\Modules\Operations\Application\Visits\VehicleAuthorization\VisitVehicleAuthorizationException;
use App\Modules\Operations\Domain\Visits\VisitVehicleAuthorizationStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleAuthorizationRequestRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleRecord;
use App\Support\Contracts\TransactionManager;
use App\Support\Contracts\UseCase;
use Illuminate\Support\Facades\Gate;

final readonly class DecideVisitVehicleAuthorizationUseCase implements UseCase
{
    public function __construct(
        private TransactionManager $transactions,
    ) {}

    public function execute(
        DecideVisitVehicleAuthorizationCommand $command
    ): VisitVehicleAuthorizationRequestRecord {
        return $this->transactions->run(
            function () use ($command): VisitVehicleAuthorizationRequestRecord {
                if (! in_array(
                    $command->decision,
                    [
                        VisitVehicleAuthorizationStatus::Authorized,
                        VisitVehicleAuthorizationStatus::Rejected,
                    ],
                    true
                )) {
                    throw VisitVehicleAuthorizationException::invalidDecision();
                }

                $user = User::query()->find(
                    $command->decidedByUserId
                );

                if (! $user instanceof User) {
                    throw VisitVehicleAuthorizationException::userUnavailable();
                }

                $request =
                    VisitVehicleAuthorizationRequestRecord::query()
                        ->with('visit')
                        ->lockForUpdate()
                        ->find($command->requestId);

                if (
                    ! $request
                    instanceof VisitVehicleAuthorizationRequestRecord
                ) {
                    throw VisitVehicleAuthorizationException::requestNotFound();
                }

                if (
                    $request->tenant_id !== $command->tenantId
                    || $request->organization_id
                        !== $command->organizationId
                    || $request->visit === null
                ) {
                    throw VisitVehicleAuthorizationException::contextMismatch();
                }

                if (
                    ! Gate::forUser($user)->allows(
                        'authorizeVehicleEntry',
                        $request->visit
                    )
                ) {
                    throw VisitVehicleAuthorizationException::authorizationDenied();
                }

                if ($request->status->isFinal()) {
                    if ($request->status === $command->decision) {
                        return $request;
                    }

                    throw VisitVehicleAuthorizationException::requestAlreadyDecided();
                }

                if (
                    $command->decision
                        === VisitVehicleAuthorizationStatus::Rejected
                    && blank($command->notes)
                ) {
                    throw VisitVehicleAuthorizationException::rejectionReasonRequired();
                }

                $vehicle = VisitVehicleRecord::query()
                    ->lockForUpdate()
                    ->find($request->visit_vehicle_id);

                if (! $vehicle instanceof VisitVehicleRecord) {
                    throw VisitVehicleAuthorizationException::vehicleNotFound();
                }

                if ($vehicle->visit_id !== $request->visit_id) {
                    throw VisitVehicleAuthorizationException::contextMismatch();
                }

                $decidedAt = $command->decidedAt ?? now();

                $notes = filled($command->notes)
                    ? trim((string) $command->notes)
                    : null;

                $request->fill([
                    'status' => $command->decision,
                    'pending_marker' => null,
                    'decided_by_user_id' => $user->id,
                    'decided_by_name' => $user->name,
                    'decision_notes' => $notes,
                    'decided_at' => $decidedAt,
                ]);

                $request->save();

                $authorized =
                    $command->decision
                    === VisitVehicleAuthorizationStatus::Authorized;

                $vehicle->fill([
                    'entry_authorized' => $authorized,
                    'entry_authorized_by' => $authorized
                        ? $user->id
                        : null,
                    'entry_authorized_at' => $authorized
                        ? $decidedAt
                        : null,
                ]);

                $vehicle->save();

                return $request;
            }
        );
    }
}
