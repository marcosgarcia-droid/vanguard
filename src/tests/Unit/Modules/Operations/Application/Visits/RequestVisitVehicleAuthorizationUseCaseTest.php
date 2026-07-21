<?php

namespace Tests\Unit\Modules\Operations\Application\Visits;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\Visits\RequestVehicleAuthorization\RequestVisitVehicleAuthorizationCommand;
use App\Modules\Operations\Application\Visits\RequestVehicleAuthorization\RequestVisitVehicleAuthorizationUseCase;
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
use Tests\TestCase;

class RequestVisitVehicleAuthorizationUseCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_pending_vehicle_authorization_request(): void
    {
        $tenant = TenantRecord::query()->create([
            'name' => 'GRUPO VEÍCULO',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE VEÍCULO LTDA',
            'display_name' => 'UNIDADE VEÍCULO',
        ]);

        $operator = User::factory()->create([
            'name' => 'OPERADOR DA PORTARIA',
        ]);

        $operator->organizations()->attach(
            $organization->id,
            [
                'role' => 'operator',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE COM VEÍCULO',
            'status' => VisitorStatus::Active,
        ]);

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => VisitStatus::Scheduled,
            'purpose' => 'ENTREGA OPERACIONAL',
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

        $request = app(
            RequestVisitVehicleAuthorizationUseCase::class
        )->execute(
            new RequestVisitVehicleAuthorizationCommand(
                visitVehicleId: $vehicle->id,
                tenantId: $tenant->id,
                organizationId: $organization->id,
                requestedByUserId: $operator->id,
                idempotencyKey: 'vehicle-request-'.$visit->id,
                notes: '  Necessário acessar o pátio de descarga.  ',
                requestedAt: new DateTimeImmutable(
                    '2026-07-21 11:00:00'
                ),
            )
        );

        $repeatedRequest = app(
            RequestVisitVehicleAuthorizationUseCase::class
        )->execute(
            new RequestVisitVehicleAuthorizationCommand(
                visitVehicleId: $vehicle->id,
                tenantId: $tenant->id,
                organizationId: $organization->id,
                requestedByUserId: $operator->id,
                idempotencyKey: 'vehicle-request-'.$visit->id,
                notes: 'Texto repetido que não deve criar outro registro.',
            )
        );

        $this->assertTrue($repeatedRequest->is($request));
        $this->assertDatabaseCount(
            'visit_vehicle_authorization_requests',
            1
        );

        $this->assertSame(
            VisitVehicleAuthorizationStatus::Pending,
            $request->status
        );

        $this->assertTrue($request->pending_marker);
        $this->assertSame($tenant->id, $request->tenant_id);
        $this->assertSame(
            $organization->id,
            $request->organization_id
        );
        $this->assertSame($visit->id, $request->visit_id);
        $this->assertSame(
            $vehicle->id,
            $request->visit_vehicle_id
        );
        $this->assertSame(
            $operator->id,
            $request->requested_by_user_id
        );
        $this->assertSame(
            'OPERADOR DA PORTARIA',
            $request->requested_by_name
        );
        $this->assertSame(
            'Necessário acessar o pátio de descarga.',
            $request->request_notes
        );
        $this->assertSame(
            '2026-07-21 11:00:00',
            $request->requested_at?->format('Y-m-d H:i:s')
        );
        $this->assertFalse(
            $vehicle->fresh()->entry_authorized
        );

        $this->assertDatabaseHas(
            'visit_vehicle_authorization_requests',
            [
                'id' => $request->id,
                'status' => 'pending',
                'pending_marker' => true,
            ]
        );

        $this->assertInstanceOf(
            VisitVehicleAuthorizationRequestRecord::class,
            $request
        );

        $this->expectException(
            VisitVehicleAuthorizationException::class
        );

        $this->expectExceptionMessage(
            'Já existe uma solicitação de autorização pendente para este veículo.'
        );

        app(
            RequestVisitVehicleAuthorizationUseCase::class
        )->execute(
            new RequestVisitVehicleAuthorizationCommand(
                visitVehicleId: $vehicle->id,
                tenantId: $tenant->id,
                organizationId: $organization->id,
                requestedByUserId: $operator->id,
                idempotencyKey: 'second-vehicle-request-'.$visit->id,
            )
        );
    }

    public function test_it_blocks_a_new_request_after_the_vehicle_authorization_was_decided(): void
    {
        $tenant = TenantRecord::query()->create([
            'name' => 'GRUPO DECISÃO FINAL',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE DECISÃO FINAL LTDA',
            'display_name' => 'UNIDADE DECISÃO FINAL',
        ]);

        $operator = User::factory()->create([
            'name' => 'OPERADOR DECISÃO FINAL',
        ]);

        $operator->organizations()->attach(
            $organization->id,
            [
                'role' => 'operator',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE DECISÃO FINAL',
            'status' => VisitorStatus::Active,
        ]);

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => VisitStatus::Scheduled,
            'purpose' => 'VALIDAR BLOQUEIO APÓS RECUSA',
            'expected_start_at' => now()->addHour(),
        ]);

        $vehicle = VisitVehicleRecord::query()->create([
            'visit_id' => $visit->id,
            'plate' => 'REC1D23',
            'entry_authorized' => false,
        ]);

        VisitVehicleAuthorizationRequestRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visit_id' => $visit->id,
            'visit_vehicle_id' => $vehicle->id,
            'status' => VisitVehicleAuthorizationStatus::Rejected,
            'pending_marker' => null,
            'idempotency_key' => 'final-rejected-'.$visit->id,
            'requested_by_user_id' => $operator->id,
            'requested_by_name' => $operator->name,
            'requested_at' => now()->subMinute(),
            'decided_by_user_id' => $operator->id,
            'decided_by_name' => 'GESTOR RESPONSÁVEL',
            'decision_notes' => 'Entrada recusada.',
            'decided_at' => now(),
        ]);

        $this->expectException(
            VisitVehicleAuthorizationException::class
        );

        $this->expectExceptionMessage(
            'A autorização de entrada deste veículo já foi decidida.'
        );

        app(
            RequestVisitVehicleAuthorizationUseCase::class
        )->execute(
            new RequestVisitVehicleAuthorizationCommand(
                visitVehicleId: $vehicle->id,
                tenantId: $tenant->id,
                organizationId: $organization->id,
                requestedByUserId: $operator->id,
                idempotencyKey: 'new-request-after-rejection-'.$visit->id,
            )
        );
    }

    public function test_it_rejects_a_user_without_access_to_the_organization(): void
    {
        $tenant = TenantRecord::query()->create([
            'name' => 'GRUPO RESTRITO',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE RESTRITA LTDA',
            'display_name' => 'UNIDADE RESTRITA',
        ]);

        $unauthorizedUser = User::factory()->create([
            'name' => 'USUÁRIO SEM ACESSO',
        ]);

        $this->expectException(
            VisitVehicleAuthorizationException::class
        );

        $this->expectExceptionMessage(
            'O veículo ou a solicitação não pertence ao contexto empresarial informado.'
        );

        app(
            RequestVisitVehicleAuthorizationUseCase::class
        )->execute(
            new RequestVisitVehicleAuthorizationCommand(
                visitVehicleId: 999999,
                tenantId: $tenant->id,
                organizationId: $organization->id,
                requestedByUserId: $unauthorizedUser->id,
                idempotencyKey: 'unauthorized-vehicle-request',
            )
        );
    }
}
