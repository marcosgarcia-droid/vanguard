<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClassificationOptionRecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_but_not_manage_classifications(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $tenant = TenantRecord::query()->create([
            'name' => 'AGRONORTE',
            'status' => 'active',
        ]);

        $user = $this->createUserWithPermissions($tenant, [
            'ViewAny:ClassificationOptionRecord',
            'View:ClassificationOptionRecord',
        ]);

        $classification = ClassificationOptionRecord::query()->create([
            'tenant_id' => $tenant->id,
            'category' => 'partner_profile',
            'code' => 'supplier',
            'name' => 'Fornecedor',
            'status' => 'active',
            'is_system' => true,
        ]);

        $this->assertTrue($user->can('viewAny', ClassificationOptionRecord::class));
        $this->assertTrue($user->can('view', $classification));
        $this->assertFalse($user->can('create', ClassificationOptionRecord::class));
        $this->assertFalse($user->can('update', $classification));
        $this->assertFalse($user->can('delete', $classification));
    }

    public function test_user_cannot_view_classification_from_another_tenant(): void
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
            'ViewAny:ClassificationOptionRecord',
            'View:ClassificationOptionRecord',
        ]);

        $classification = ClassificationOptionRecord::query()->create([
            'tenant_id' => $otherTenant->id,
            'category' => 'partner_profile',
            'code' => 'supplier',
            'name' => 'Fornecedor',
            'status' => 'active',
        ]);

        $this->assertFalse($user->can('view', $classification));
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

        $role = Role::findOrCreate('classification_viewer_test', $guard);
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
