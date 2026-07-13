<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitorRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_relates_visitor_to_scope_partner_documents_and_contacts(): void
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
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

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'partner_id' => $partner->id,
            'visitor_code' => 'VIS-001',
            'full_name' => 'Pessoa Visitante',
            'preferred_name' => 'Visitante',
            'status' => VisitorStatus::Active,
        ]);

        $visitor->documents()->create([
            'type' => 'cpf',
            'number' => '123.456.789-09',
            'is_primary' => true,
        ]);

        $visitor->contacts()->create([
            'type' => 'mobile',
            'value' => '(38) 99999-0000',
            'is_primary' => true,
        ]);

        $loaded = VisitorRecord::query()
            ->with([
                'tenant',
                'organization',
                'partner',
                'documents',
                'contacts',
            ])
            ->findOrFail($visitor->id);

        $this->assertNotEmpty($loaded->id);
        $this->assertTrue($loaded->tenant->is($tenant));
        $this->assertTrue($loaded->organization->is($organization));
        $this->assertTrue($loaded->partner->is($partner));

        $this->assertSame('Visitante', $loaded->display_name);
        $this->assertSame(
            '12345678909',
            $loaded->official_document_number
        );
        $this->assertSame(
            '38999990000',
            $loaded->primary_contact_display
        );

        $this->assertSame(
            VisitorStatus::Active,
            $loaded->status
        );
    }

    public function test_it_detects_duplicate_document_inside_same_unit(): void
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

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'Primeiro Visitante',
            'status' => VisitorStatus::Active,
        ]);

        $visitor->documents()->create([
            'type' => 'cpf',
            'number' => '123.456.789-09',
            'is_primary' => true,
        ]);

        $this->assertTrue(
            VisitorRecord::documentExistsForOrganization(
                $tenant->id,
                $organization->id,
                'cpf',
                '12345678909'
            )
        );

        $this->assertFalse(
            VisitorRecord::documentExistsForOrganization(
                $tenant->id,
                $organization->id,
                'cpf',
                '12345678909',
                $visitor->id
            )
        );
    }
}
