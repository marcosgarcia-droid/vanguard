<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_relates_visit_to_operational_context_and_responsible_users(): void
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
            'authorized_by' => $operator->id,
            'authorized_at' => now(),
        ]);

        $loaded = VisitRecord::query()
            ->with([
                'tenant',
                'organization',
                'visitor',
                'hostEmployee',
                'partner',
                'authorizedBy',
            ])
            ->findOrFail($visit->id);

        $this->assertNotEmpty($loaded->id);
        $this->assertTrue($loaded->tenant->is($tenant));
        $this->assertTrue($loaded->organization->is($organization));
        $this->assertTrue($loaded->visitor->is($visitor));
        $this->assertTrue($loaded->hostEmployee->is($host));
        $this->assertTrue($loaded->partner->is($partner));
        $this->assertTrue($loaded->authorizedBy->is($operator));

        $this->assertSame(
            VisitStatus::Authorized,
            $loaded->status
        );

        $this->assertFalse($loaded->status->isFinal());
        $this->assertNotNull($loaded->expected_start_at);
        $this->assertNotNull($loaded->authorized_at);
    }
}
