<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecordPolicy;
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
        $tenant = $this->tenant();

        $user = $this->userWithPermissions([
            'ViewAny:EmployeeRecord',
            'View:EmployeeRecord',
            'Create:EmployeeRecord',
            'Update:EmployeeRecord',
        ], $tenant);

        $employee = $this->employee($tenant);

        $policy = new EmployeeRecordPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $employee));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $employee));
        $this->assertFalse($policy->delete($user, $employee));
    }

    public function test_user_cannot_view_employee_from_another_tenant(): void
    {
        $allowedTenant = $this->tenant('AGRONORTE');
        $blockedTenant = $this->tenant('OUTRO GRUPO');

        $user = $this->userWithPermissions([
            'ViewAny:EmployeeRecord',
            'View:EmployeeRecord',
            'Update:EmployeeRecord',
        ], $allowedTenant);

        $employee = $this->employee($blockedTenant);

        $policy = new EmployeeRecordPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertFalse($policy->view($user, $employee));
        $this->assertFalse($policy->update($user, $employee));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions, TenantRecord $tenant): User
    {
        $role = Role::findOrCreate('test_role_'.Str::random(8), 'web');

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        $tenant->users()->attach($user->id, [
            'role' => 'member',
            'is_owner' => false,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function tenant(string $name = 'AGRONORTE'): TenantRecord
    {
        return TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function employee(TenantRecord $tenant): EmployeeRecord
    {
        return EmployeeRecord::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'João Silva',
            'status' => 'active',
            'employment_type' => 'employee',
        ]);
    }
}
