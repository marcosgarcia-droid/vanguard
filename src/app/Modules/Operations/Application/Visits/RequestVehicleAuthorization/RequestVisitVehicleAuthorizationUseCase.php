<?php

namespace App\Modules\Operations\Application\Visits\RequestVehicleAuthorization;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\Visits\VehicleAuthorization\VisitVehicleAuthorizationException;
use App\Modules\Operations\Domain\Visits\VisitVehicleAuthorizationStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleAuthorizationRequestRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleRecord;
use App\Support\Contracts\TransactionManager;
use App\Support\Contracts\UseCase;

final readonly class RequestVisitVehicleAuthorizationUseCase implements UseCase
{
    public function __construct(
        private TransactionManager $transactions,
        private TenantContext $tenantContext,
    ) {}

    public function execute(
        RequestVisitVehicleAuthorizationCommand $command
    ): VisitVehicleAuthorizationRequestRecord {
        return $this->transactions->run(
            function () use ($command): VisitVehicleAuthorizationRequestRecord {
                $user = User::query()->find(
                    $command->requestedByUserId
                );

                if (! $user instanceof User) {
                    throw VisitVehicleAuthorizationException::userUnavailable();
                }

                $tenant = TenantRecord::query()
                    ->whereKey($command->tenantId)
                    ->where('status', 'active')
                    ->first();

                if (
                    ! $tenant instanceof TenantRecord
                    || ! $this->tenantContext->canSelectTenant(
                        $user,
                        $tenant
                    )
                    || ! $this->tenantContext->hasOrganizationAccess(
                        $user,
                        $command->organizationId
                    )
                ) {
                    throw VisitVehicleAuthorizationException::contextMismatch();
                }

                $existingRequest =
                    VisitVehicleAuthorizationRequestRecord::query()
                        ->where(
                            'idempotency_key',
                            $command->idempotencyKey
                        )
                        ->first();

                if ($existingRequest !== null) {
                    if (
                        $existingRequest->visit_vehicle_id
                            !== $command->visitVehicleId
                        || $existingRequest->tenant_id
                            !== $command->tenantId
                        || $existingRequest->organization_id
                            !== $command->organizationId
                    ) {
                        throw VisitVehicleAuthorizationException::contextMismatch();
                    }

                    return $existingRequest;
                }

                $vehicle = VisitVehicleRecord::query()
                    ->with('visit')
                    ->lockForUpdate()
                    ->find($command->visitVehicleId);

                if (! $vehicle instanceof VisitVehicleRecord) {
                    throw VisitVehicleAuthorizationException::vehicleNotFound();
                }

                $visit = $vehicle->visit;

                if (
                    $visit === null
                    || $visit->tenant_id !== $command->tenantId
                    || $visit->organization_id !== $command->organizationId
                ) {
                    throw VisitVehicleAuthorizationException::contextMismatch();
                }

                $existingVehicleRequest =
                    VisitVehicleAuthorizationRequestRecord::query()
                        ->where(
                            'visit_vehicle_id',
                            $vehicle->id
                        )
                        ->latest('requested_at')
                        ->first();

                if ($existingVehicleRequest !== null) {
                    if ($existingVehicleRequest->pending_marker) {
                        throw VisitVehicleAuthorizationException::pendingRequestAlreadyExists();
                    }

                    throw VisitVehicleAuthorizationException::vehicleAuthorizationAlreadyDecided();
                }

                $notes = filled($command->notes)
                    ? trim((string) $command->notes)
                    : null;

                return VisitVehicleAuthorizationRequestRecord::query()
                    ->create([
                        'tenant_id' => $visit->tenant_id,
                        'organization_id' => $visit->organization_id,
                        'visit_id' => $visit->id,
                        'visit_vehicle_id' => $vehicle->id,
                        'status' => VisitVehicleAuthorizationStatus::Pending,
                        'pending_marker' => true,
                        'idempotency_key' => $command->idempotencyKey,
                        'requested_by_user_id' => $user->id,
                        'requested_by_name' => $user->name,
                        'request_notes' => $notes,
                        'requested_at' => $command->requestedAt ?? now(),
                    ]);
            }
        );
    }
}
