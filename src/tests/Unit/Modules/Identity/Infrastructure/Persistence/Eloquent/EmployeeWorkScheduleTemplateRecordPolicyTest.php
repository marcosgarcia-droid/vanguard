<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeeWorkScheduleTemplateRecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_but_not_manage_work_schedule_templates(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $tenant = TenantRecord::query()->create([
            'name' => 'AGRONORTE',
            'status' => 'active',
        ]);

        $user = $this->createUserWithPermissions($tenant, [
            'ViewAny:EmployeeWorkScheduleTemplateRecord',
            'View:EmployeeWorkScheduleTemplateRecord',
        ]);

        $template = EmployeeWorkScheduleTemplateRecord::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'administrativo_44h',
            'name' => 'Administrativo 44h',
            'type' => 'standard',
            'description' => '08:00 às 12:00 - 13:00 às 17:48 - SAB DOM DSR',
            'status' => 'active',
            'is_system' => true,
        ]);

        $this->assertTrue($user->can('viewAny', EmployeeWorkScheduleTemplateRecord::class));
        $this->assertTrue($user->can('view', $template));
        $this->assertFalse($user->can('create', EmployeeWorkScheduleTemplateRecord::class));
        $this->assertFalse($user->can('update', $template));
        $this->assertFalse($user->can('delete', $template));
    }

    public function test_user_cannot_view_work_schedule_template_from_another_tenant(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $tenant = TenantRecord::query()->create([
            'name' => 'AGRONORTE',
            'status' => 'active',
        ]);

        $otherTenant = TenantRecord::query()->create([
            'name' => 'OUTRO TENANT',
            'status' => 'active',
        ]);

        $user = $this->createUserWithPermissions($tenant, [
            'ViewAny:EmployeeWorkScheduleTemplateRecord',
            'View:EmployeeWorkScheduleTemplateRecord',
        ]);

        $template = EmployeeWorkScheduleTemplateRecord::query()->create([
            'tenant_id' => $otherTenant->id,
            'code' => 'administrativo_44h',
            'name' => 'Administrativo 44h',
            'type' => 'standard',
            'status' => 'active',
        ]);

        $this->assertFalse($user->can('view', $template));
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function createUserWithPermissions(TenantRecord $tenant, array $permissions): User
    {
        $guard = 'web';

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        $role = Role::findOrCreate('work_schedule_template_viewer_test', $guard);
        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        $tenant->users()->attach($user->id, [
            'role' => 'manager',
            'is_owner' => false,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
