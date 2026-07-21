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

class DecideVisitVehicleAuthorizationUseCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_authorizes_vehicle_entry_safely_and_idempotently(): void
    {
        $tenant = TenantRecord::query()->create([
            'name' => 'GRUPO DECISÃO VEÍCULO',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE DECISÃO VEÍCULO LTDA',
            'display_name' => 'UNIDADE DECISÃO VEÍCULO',
        ]);

        Permission::findOrCreate(
            'AuthorizeVehicleEntry:VisitRecord',
            'web'
        );

        $role = Role::findOrCreate(
            'vehicle_authorizer_test',
            'web'
        );

        $role->syncPermissions([
            'AuthorizeVehicleEntry:VisitRecord',
        ]);

        $manager = User::factory()->create([
            'name' => 'GESTOR AUTORIZADOR',
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
            'full_name' => 'VISITANTE AUTORIZADO',
            'status' => VisitorStatus::Active,
        ]);

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => VisitStatus::Scheduled,
            'purpose' => 'ENTREGA NO PÁTIO',
            'expected_start_at' => now()->addHour(),
        ]);

        $vehicle = VisitVehicleRecord::query()->create([
            'visit_id' => $visit->id,
            'plate' => 'ABC1D23',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'color' => 'Branca',
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
                    'idempotency_key' => 'decision-request-'.$visit->id,
                    'requested_by_user_id' => $requester->id,
                    'requested_by_name' => $requester->name,
                    'requested_at' => now(),
                ]);

        $unauthorizedUser = User::factory()->create([
            'name' => 'USUÁRIO SEM PERMISSÃO',
        ]);

        $unauthorizedUser->organizations()->attach(
            $organization->id,
            [
                'role' => 'operator',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        try {
            app(
                DecideVisitVehicleAuthorizationUseCase::class
            )->execute(
                new DecideVisitVehicleAuthorizationCommand(
                    requestId: $request->id,
                    tenantId: $tenant->id,
                    organizationId: $organization->id,
                    decidedByUserId: $unauthorizedUser->id,
                    decision: VisitVehicleAuthorizationStatus::Authorized,
                    notes: 'Tentativa sem permissão.',
                )
            );

            $this->fail(
                'O usuário sem permissão deveria ter sido bloqueado.'
            );
        } catch (VisitVehicleAuthorizationException $exception) {
            $this->assertSame(
                'O usuário não possui permissão para decidir a entrada do veículo.',
                $exception->getMessage()
            );
        }

        $requestAfterDeniedDecision = $request->fresh();
        $vehicleAfterDeniedDecision = $vehicle->fresh();

        $this->assertSame(
            VisitVehicleAuthorizationStatus::Pending,
            $requestAfterDeniedDecision->status
        );

        $this->assertTrue(
            $requestAfterDeniedDecision->pending_marker
        );

        $this->assertFalse(
            $vehicleAfterDeniedDecision->entry_authorized
        );

        $otherOrganization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'OUTRA UNIDADE DO GRUPO LTDA',
            'display_name' => 'OUTRA UNIDADE DO GRUPO',
        ]);

        $crossUnitManager = User::factory()->create([
            'name' => 'GESTOR DE OUTRA UNIDADE',
        ]);

        $crossUnitManager->assignRole($role);

        $crossUnitManager->organizations()->attach(
            $otherOrganization->id,
            [
                'role' => 'manager',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        try {
            app(
                DecideVisitVehicleAuthorizationUseCase::class
            )->execute(
                new DecideVisitVehicleAuthorizationCommand(
                    requestId: $request->id,
                    tenantId: $tenant->id,
                    organizationId: $organization->id,
                    decidedByUserId: $crossUnitManager->id,
                    decision: VisitVehicleAuthorizationStatus::Authorized,
                    notes: 'Tentativa a partir de outra unidade.',
                )
            );

            $this->fail(
                'O gestor de outra unidade deveria ter sido bloqueado.'
            );
        } catch (VisitVehicleAuthorizationException $exception) {
            $this->assertSame(
                'O usuário não possui permissão para decidir a entrada do veículo.',
                $exception->getMessage()
            );
        }

        $requestAfterCrossUnitAttempt = $request->fresh();
        $vehicleAfterCrossUnitAttempt = $vehicle->fresh();

        $this->assertSame(
            VisitVehicleAuthorizationStatus::Pending,
            $requestAfterCrossUnitAttempt->status
        );

        $this->assertTrue(
            $requestAfterCrossUnitAttempt->pending_marker
        );

        $this->assertFalse(
            $vehicleAfterCrossUnitAttempt->entry_authorized
        );

        $decidedRequest = app(
            DecideVisitVehicleAuthorizationUseCase::class
        )->execute(
            new DecideVisitVehicleAuthorizationCommand(
                requestId: $request->id,
                tenantId: $tenant->id,
                organizationId: $organization->id,
                decidedByUserId: $manager->id,
                decision: VisitVehicleAuthorizationStatus::Authorized,
                notes: '  Entrada liberada para descarga.  ',
                decidedAt: new DateTimeImmutable(
                    '2026-07-21 12:00:00'
                ),
            )
        );

        $this->assertSame(
            VisitVehicleAuthorizationStatus::Authorized,
            $decidedRequest->status
        );

        $this->assertNull($decidedRequest->pending_marker);
        $this->assertSame(
            $manager->id,
            $decidedRequest->decided_by_user_id
        );
        $this->assertSame(
            'GESTOR AUTORIZADOR',
            $decidedRequest->decided_by_name
        );
        $this->assertSame(
            'Entrada liberada para descarga.',
            $decidedRequest->decision_notes
        );
        $this->assertSame(
            '2026-07-21 12:00:00',
            $decidedRequest->decided_at?->format('Y-m-d H:i:s')
        );

        $authorizedVehicle = $vehicle->fresh();

        $this->assertTrue($authorizedVehicle->entry_authorized);
        $this->assertSame(
            $manager->id,
            $authorizedVehicle->entry_authorized_by
        );
        $this->assertSame(
            '2026-07-21 12:00:00',
            $authorizedVehicle->entry_authorized_at?->format(
                'Y-m-d H:i:s'
            )
        );

        $repeatedDecision = app(
            DecideVisitVehicleAuthorizationUseCase::class
        )->execute(
            new DecideVisitVehicleAuthorizationCommand(
                requestId: $request->id,
                tenantId: $tenant->id,
                organizationId: $organization->id,
                decidedByUserId: $manager->id,
                decision: VisitVehicleAuthorizationStatus::Authorized,
                notes: 'Esta repetição não deve alterar a decisão.',
            )
        );

        $this->assertTrue(
            $repeatedDecision->is($decidedRequest)
        );

        $this->assertSame(
            'Entrada liberada para descarga.',
            $repeatedDecision->decision_notes
        );

        $this->expectException(
            VisitVehicleAuthorizationException::class
        );

        $this->expectExceptionMessage(
            'A solicitação de autorização do veículo já foi decidida.'
        );

        app(
            DecideVisitVehicleAuthorizationUseCase::class
        )->execute(
            new DecideVisitVehicleAuthorizationCommand(
                requestId: $request->id,
                tenantId: $tenant->id,
                organizationId: $organization->id,
                decidedByUserId: $manager->id,
                decision: VisitVehicleAuthorizationStatus::Rejected,
                notes: 'Tentativa de inverter a decisão.',
            )
        );
    }
}
