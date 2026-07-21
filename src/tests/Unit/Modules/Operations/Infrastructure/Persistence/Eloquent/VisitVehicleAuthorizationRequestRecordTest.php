<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Domain\Visits\VisitVehicleAuthorizationStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleAuthorizationRequestRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleRecord;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class VisitVehicleAuthorizationRequestRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_an_audited_pending_vehicle_authorization_request(): void
    {
        $tenant = TenantRecord::query()->create([
            'name' => 'GRUPO VEÍCULO SINTÉTICO',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE VEÍCULO SINTÉTICA LTDA',
            'display_name' => 'UNIDADE VEÍCULO SINTÉTICA',
        ]);

        $requester = User::factory()->create([
            'name' => 'OPERADOR SINTÉTICO',
        ]);

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
            'purpose' => 'VISITA COM SOLICITAÇÃO DE VEÍCULO',
            'expected_start_at' => now()->addHour(),
        ]);

        $vehicle = VisitVehicleRecord::query()->create([
            'visit_id' => $visit->id,
            'plate' => 'ABC1D23',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'color' => 'Prata',
            'entry_authorized' => false,
        ]);

        $request = VisitVehicleAuthorizationRequestRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visit_id' => $visit->id,
            'visit_vehicle_id' => $vehicle->id,
            'status' => VisitVehicleAuthorizationStatus::Pending,
            'idempotency_key' => 'vehicle-request-'.$visit->id,
            'requested_by_user_id' => $requester->id,
            'requested_by_name' => $requester->name,
            'request_notes' => 'Entrada necessária para entrega.',
            'requested_at' => now(),
        ]);

        $loaded = VisitVehicleAuthorizationRequestRecord::query()
            ->with([
                'tenant',
                'organization',
                'visit',
                'vehicle',
                'requestedBy',
            ])
            ->findOrFail($request->id);

        $this->assertSame(
            VisitVehicleAuthorizationStatus::Pending,
            $loaded->status
        );

        $this->assertTrue($loaded->status->isPending());
        $this->assertTrue($loaded->pending_marker);
        $this->assertTrue($loaded->tenant->is($tenant));
        $this->assertTrue($loaded->organization->is($organization));
        $this->assertTrue($loaded->visit->is($visit));
        $this->assertTrue($loaded->vehicle->is($vehicle));
        $this->assertTrue($loaded->requestedBy->is($requester));
        $this->assertNotNull($loaded->requested_at);
        $this->assertNull($loaded->decided_at);
        $this->assertFalse($vehicle->fresh()->entry_authorized);

        $activity = Activity::query()
            ->where(
                'subject_type',
                VisitVehicleAuthorizationRequestRecord::class
            )
            ->where('subject_id', $request->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            VisitRecord::class,
            data_get(
                $activity->properties,
                'vanguard_parent_type'
            )
        );

        $this->assertSame(
            $visit->id,
            data_get(
                $activity->properties,
                'vanguard_parent_id'
            )
        );

        $this->expectException(QueryException::class);

        VisitVehicleAuthorizationRequestRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visit_id' => $visit->id,
            'visit_vehicle_id' => $vehicle->id,
            'status' => VisitVehicleAuthorizationStatus::Pending,
            'idempotency_key' => 'duplicate-vehicle-request-'.$visit->id,
            'requested_by_user_id' => $requester->id,
            'requested_by_name' => $requester->name,
            'requested_at' => now(),
        ]);
    }
}
