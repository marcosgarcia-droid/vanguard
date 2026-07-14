<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitAuthorizationMethod;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_relates_visit_to_gatehouse_operational_context(): void
    {
        $tenant = TenantRecord::query()->create([
            'name' => 'GRUPO DEMONSTRAÇÃO',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE DEMONSTRAÇÃO LTDA',
            'display_name' => 'UNIDADE DEMONSTRAÇÃO',
        ]);

        $partner = PartnerRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'person_type' => 'company',
            'name' => 'PRESTADOR DEMONSTRAÇÃO LTDA',
            'status' => 'active',
        ]);

        $host = EmployeeRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'Funcionário Anfitrião',
            'employment_type' => 'employee',
            'status' => 'active',
        ]);

        $authorizer = EmployeeRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'Funcionária Autorizadora',
            'employment_type' => 'employee',
            'status' => 'active',
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'partner_id' => $partner->id,
            'full_name' => 'Pessoa Visitante',
            'status' => VisitorStatus::Active,
        ]);

        $operator = User::factory()->create();

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'host_employee_id' => $host->id,
            'partner_id' => $partner->id,
            'status' => VisitStatus::Authorized,
            'purpose' => 'Reunião operacional',
            'expected_start_at' => now()->addHour(),
            'expected_end_at' => now()->addHours(2),
            'arrived_by' => $operator->id,
            'arrived_at' => now(),
            'authorizer_employee_id' => $authorizer->id,
            'authorization_method' => VisitAuthorizationMethod::Radio,
            'authorization_notes' => 'Anfitrião estava na produção.',
            'authorized_by' => $operator->id,
            'authorized_at' => now(),
            'identity_verified_by' => $operator->id,
            'identity_verified_at' => now(),
        ]);

        $loaded = VisitRecord::query()
            ->with([
                'tenant',
                'organization',
                'visitor',
                'hostEmployee',
                'partner',
                'arrivedBy',
                'authorizerEmployee',
                'authorizationRecordedBy',
                'identityVerifiedBy',
            ])
            ->findOrFail($visit->id);

        $this->assertNotEmpty($loaded->id);
        $this->assertTrue($loaded->tenant->is($tenant));
        $this->assertTrue($loaded->organization->is($organization));
        $this->assertTrue($loaded->visitor->is($visitor));
        $this->assertTrue($loaded->hostEmployee->is($host));
        $this->assertTrue($loaded->partner->is($partner));
        $this->assertTrue($loaded->arrivedBy->is($operator));
        $this->assertTrue(
            $loaded->authorizerEmployee->is($authorizer)
        );
        $this->assertTrue(
            $loaded->authorizationRecordedBy->is($operator)
        );
        $this->assertTrue(
            $loaded->identityVerifiedBy->is($operator)
        );

        $this->assertSame(
            VisitStatus::Authorized,
            $loaded->status
        );

        $this->assertSame(
            VisitAuthorizationMethod::Radio,
            $loaded->authorization_method
        );

        $this->assertTrue($loaded->status->canCheckIn());
        $this->assertFalse($loaded->status->isFinal());
        $this->assertNotNull($loaded->arrived_at);
        $this->assertNotNull($loaded->authorized_at);
        $this->assertNotNull($loaded->identity_verified_at);
    }

    public function test_operational_statuses_prioritize_the_simple_flow(): void
    {
        $this->assertSame([
            'scheduled' => 'Agendada',
            'pending_authorization' => 'Aguardando autorização',
            'authorized' => 'Autorizada',
            'in_progress' => 'Em andamento',
            'completed' => 'Concluída',
            'cancelled' => 'Cancelada',
        ], VisitStatus::operationalOptions());

        $this->assertTrue(
            VisitStatus::Scheduled->canRegisterArrival()
        );

        $this->assertTrue(
            VisitStatus::PendingAuthorization->canAuthorize()
        );

        $this->assertTrue(
            VisitStatus::Rejected->canAuthorize()
        );

        $this->assertFalse(
            VisitStatus::Rejected->isFinal()
        );

        $this->assertTrue(
            VisitStatus::InProgress->canCheckOut()
        );

        $this->assertFalse(
            VisitStatus::Completed->canCancel()
        );
    }
}
