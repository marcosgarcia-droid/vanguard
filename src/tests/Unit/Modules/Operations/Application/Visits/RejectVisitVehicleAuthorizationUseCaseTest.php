<?php

namespace Tests\Unit\Modules\Operations\Application\Visits;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\Visits\DecideVehicleAuthorization\DecideVisitVehicleAuthorizationCommand;
use App\Modules\Operations\Application\Visits\DecideVehicleAuthorization\DecideVisitVehicleAuthorizationUseCase;
use App\Modules\Operations\Application\Visits\VehicleAuthorization\VisitVehicleAuthorizationException;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Domain\Visits\VisitVehicleAuthorizationStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleAuthorizationRequestRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RejectVisitVehicleAuthorizationUseCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_vehicle_entry_and_preserves_the_reason(): void
    {
        $tenant = TenantRecord::query()->create([
            'name' => 'GRUPO RECUSA VEÍCULO',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE RECUSA VEÍCULO LTDA',
            'display_name' => 'UNIDADE RECUSA VEÍCULO',
        ]);

        Permission::findOrCreate(
            'AuthorizeVehicleEntry:VisitRecord',
            'web'
        );

        $role = Role::findOrCreate(
            'vehicle_rejector_test',
            'web'
        );

        $role->syncPermissions([
            'AuthorizeVehicleEntry:VisitRecord',
        ]);

        $manager = User::factory()->create([
            'name' => 'GESTOR RESPONSÁVEL',
        ]);

        $manager->assignRole($role);

        $manager->organizations()->attach(
            $organization->id,
            [
                'role' => 'manager',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $requester = User::factory()->create([
            'name' => 'OPERADOR SOLICITANTE',
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE COM ENTRADA RECUSADA',
            'status' => VisitorStatus::Active,
        ]);

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => VisitStatus::Scheduled,
            'purpose' => 'ACESSO AO PÁTIO',
            'expected_start_at' => now()->addHour(),
        ]);

        $vehicle = VisitVehicleRecord::query()->create([
            'visit_id' => $visit->id,
            'plate' => 'DEF4G56',
            'brand' => 'Volkswagen',
            'model' => 'Delivery',
            'color' => 'Branco',
            'entry_authorized' => false,
        ]);

        $request =
            VisitVehicleAuthorizationRequestRecord::query()
                ->create([
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'visit_id' => $visit->id,
                    'visit_vehicle_id' => $vehicle->id,
                    'status' => VisitVehicleAuthorizationStatus::Pending,
                    'pending_marker' => true,
                    'idempotency_key' => 'rejection-request-'.$visit->id,
                    'requested_by_user_id' => $requester->id,
                    'requested_by_name' => $requester->name,
                    'requested_at' => now(),
                ]);

        try {
            app(
                DecideVisitVehicleAuthorizationUseCase::class
            )->execute(
                new DecideVisitVehicleAuthorizationCommand(
                    requestId: $request->id,
                    tenantId: $tenant->id,
                    organizationId: $organization->id,
                    decidedByUserId: $manager->id,
                    decision: VisitVehicleAuthorizationStatus::Rejected,
                    notes: '   ',
                )
            );

            $this->fail(
                'A recusa sem justificativa deveria ter sido bloqueada.'
            );
        } catch (VisitVehicleAuthorizationException $exception) {
            $this->assertSame(
                'Informe o motivo da recusa da entrada do veículo.',
                $exception->getMessage()
            );
        }

        $requestAfterInvalidDecision = $request->fresh();
        $vehicleAfterInvalidDecision = $vehicle->fresh();

        $this->assertSame(
            VisitVehicleAuthorizationStatus::Pending,
            $requestAfterInvalidDecision->status
        );
        $this->assertTrue(
            $requestAfterInvalidDecision->pending_marker
        );
        $this->assertFalse(
            $vehicleAfterInvalidDecision->entry_authorized
        );

        $decidedRequest = app(
            DecideVisitVehicleAuthorizationUseCase::class
        )->execute(
            new DecideVisitVehicleAuthorizationCommand(
                requestId: $request->id,
                tenantId: $tenant->id,
                organizationId: $organization->id,
                decidedByUserId: $manager->id,
                decision: VisitVehicleAuthorizationStatus::Rejected,
                notes: '  Veículo não autorizado a acessar a área interna.  ',
                decidedAt: new DateTimeImmutable(
                    '2026-07-21 12:30:00'
                ),
            )
        );

        $this->assertSame(
            VisitVehicleAuthorizationStatus::Rejected,
            $decidedRequest->status
        );

        $this->assertNull($decidedRequest->pending_marker);

        $this->assertSame(
            $manager->id,
            $decidedRequest->decided_by_user_id
        );

        $this->assertSame(
            'GESTOR RESPONSÁVEL',
            $decidedRequest->decided_by_name
        );

        $this->assertSame(
            'Veículo não autorizado a acessar a área interna.',
            $decidedRequest->decision_notes
        );

        $this->assertSame(
            '2026-07-21 12:30:00',
            $decidedRequest->decided_at?->format('Y-m-d H:i:s')
        );

        $rejectedVehicle = $vehicle->fresh();

        $this->assertFalse($rejectedVehicle->entry_authorized);
        $this->assertNull($rejectedVehicle->entry_authorized_by);
        $this->assertNull($rejectedVehicle->entry_authorized_at);
    }
}
