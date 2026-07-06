<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeAddressRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeContactRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeDocumentRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_relates_employee_to_tenant_organization_user_manager_documents_addresses_and_contacts(): void
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'AGRONORTE',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'AGRONORTE NUTRICAO ANIMAL LTDA',
            'display_name' => 'AGRONORTE BARREIRAS',
        ]);

        $user = User::factory()->create();

        $manager = EmployeeRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'Maria Gestora',
            'status' => 'active',
            'employment_type' => 'employee',
        ]);

        $employee = EmployeeRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'manager_employee_id' => $manager->id,
            'employee_code' => '1001',
            'full_name' => 'João Silva',
            'preferred_name' => 'João',
            'gender' => 'male',
            'photo_disk' => 'private',
            'photo_path' => 'employees/photos/joao.jpg',
            'photo_uploaded_at' => now(),
            'position' => 'Operador',
            'status' => 'active',
            'employment_type' => 'employee',
        ]);

        EmployeeDocumentRecord::query()->create([
            'employee_id' => $employee->id,
            'type' => 'cpf',
            'number' => '12345678909',
            'is_primary' => true,
        ]);

        EmployeeAddressRecord::query()->create([
            'employee_id' => $employee->id,
            'type' => 'residential',
            'postal_code' => '39400000',
            'street' => 'Rua Teste',
            'city' => 'Montes Claros',
            'state' => 'MG',
            'is_primary' => true,
        ]);

        EmployeeContactRecord::query()->create([
            'employee_id' => $employee->id,
            'type' => 'mobile',
            'value' => '38999999999',
            'is_primary' => true,
        ]);

        $employee->refresh();

        $this->assertNotEmpty($employee->id);
        $this->assertTrue($employee->tenant->is($tenant));
        $this->assertTrue($employee->organization->is($organization));
        $this->assertTrue($employee->user->is($user));
        $this->assertTrue($employee->manager->is($manager));
        $this->assertSame('João', $employee->display_name);
        $this->assertSame('12345678909', $employee->cpf);
        $this->assertSame('38999999999', $employee->mobile_phone);
        $this->assertSame('39400000', $employee->primaryAddress()?->postal_code);
    }
}
