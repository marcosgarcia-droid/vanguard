<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartnerRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_relates_partner_to_tenant_organization_documents_addresses_and_contacts(): void
    {
        $tenant = TenantRecord::query()->create([
            'name' => 'AGRONORTE',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'display_name' => 'AGRONORTE MATRIZ',
            'legal_name' => 'AGRONORTE DEMO LTDA',
            'status' => 'active',
        ]);

        $partner = PartnerRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'partner_code' => 'PAR-001',
            'person_type' => 'company',
            'name' => 'FORNECEDOR DEMO LTDA',
            'trade_name' => 'FORNECEDOR DEMO',
            'status' => 'active',
            'profiles' => ['supplier', 'service_provider'],
        ]);

        $partner->documents()->create([
            'type' => 'cnpj',
            'number' => '12.345.678/0001-99',
            'is_primary' => true,
        ]);

        $partner->addresses()->create([
            'type' => 'operational',
            'postal_code' => '39400-000',
            'city' => 'Montes Claros',
            'state' => 'mg',
            'is_primary' => true,
        ]);

        $partner->contacts()->create([
            'type' => 'mobile',
            'label' => 'Comercial',
            'value' => '(38) 99999-0000',
            'is_primary' => true,
        ]);

        $loaded = PartnerRecord::query()
            ->with(['tenant', 'organization', 'documents', 'addresses', 'contacts'])
            ->findOrFail($partner->id);

        $this->assertSame($tenant->id, $loaded->tenant->id);
        $this->assertSame($organization->id, $loaded->organization->id);
        $this->assertSame('FORNECEDOR DEMO', $loaded->display_name);
        $this->assertSame('12345678000199', $loaded->cnpj);
        $this->assertSame('Montes Claros/MG', $loaded->city_state);
        $this->assertSame('(38) 99999-0000', $loaded->primary_contact_display);
        $this->assertSame('39400000', $loaded->primaryAddress()?->postal_code);
    }
}
