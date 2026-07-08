<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecordPolicy;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeRecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_and_update_employees_in_own_tenant(): void
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Grupo Teste',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'Unidade Teste',
            'display_name' => 'Unidade Teste',
            'unit_code' => 'UND-TEST',
        ]);

        $user = User::factory()->create();

        foreach ([
            'ViewAny:EmployeeRecord',
            'View:EmployeeRecord',
            'Create:EmployeeRecord',
            'Update:EmployeeRecord',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate('manager', 'web');
        $role->syncPermissions([
            'ViewAny:EmployeeRecord',
            'View:EmployeeRecord',
            'Create:EmployeeRecord',
            'Update:EmployeeRecord',
        ]);

        $user->assignRole($role);

        $user->tenants()->attach($tenant->id, [
            'role' => 'manager',
            'is_owner' => false,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $user->organizations()->attach($organization->id, [
            'role' => 'manager',
            'is_active' => true,
            'granted_at' => now(),
        ]);

        $employee = EmployeeRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'Funcionário Teste',
            'employment_type' => 'employee',
            'status' => 'active',
        ]);

        $policy = new EmployeeRecordPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $employee));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $employee));
        $this->assertFalse($policy->delete($user, $employee));
    }

    public function test_user_cannot_view_employee_from_another_tenant(): void
    {
        $allowedTenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Grupo Permitido',
            'status' => 'active',
        ]);

        $otherTenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Outro Grupo',
            'status' => 'active',
        ]);

        $otherOrganization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'status' => 'active',
            'legal_name' => 'Outra Unidade',
            'display_name' => 'Outra Unidade',
            'unit_code' => 'OUT-TEST',
        ]);

        $user = User::factory()->create();

        foreach ([
            'ViewAny:EmployeeRecord',
            'View:EmployeeRecord',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate('manager', 'web');
        $role->syncPermissions([
            'ViewAny:EmployeeRecord',
            'View:EmployeeRecord',
        ]);

        $user->assignRole($role);

        $user->tenants()->attach($allowedTenant->id, [
            'role' => 'manager',
            'is_owner' => false,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $employee = EmployeeRecord::query()->create([
            'tenant_id' => $otherTenant->id,
            'organization_id' => $otherOrganization->id,
            'full_name' => 'Funcionário Outro Grupo',
            'employment_type' => 'employee',
            'status' => 'active',
        ]);

        $policy = new EmployeeRecordPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertFalse($policy->view($user, $employee));
    }
}
