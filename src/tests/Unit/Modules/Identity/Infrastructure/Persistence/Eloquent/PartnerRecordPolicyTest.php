<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PartnerRecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_and_update_partners_in_own_tenant(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $tenant = TenantRecord::query()->create([
            'name' => 'AGRONORTE',
            'status' => 'active',
        ]);

        $user = $this->createUserWithPermissions($tenant, [
            'ViewAny:PartnerRecord',
            'View:PartnerRecord',
            'Create:PartnerRecord',
            'Update:PartnerRecord',
        ]);

        $partner = PartnerRecord::query()->create([
            'tenant_id' => $tenant->id,
            'person_type' => 'company',
            'name' => 'FORNECEDOR DEMO LTDA',
            'status' => 'active',
        ]);

        $this->assertTrue($user->can('viewAny', PartnerRecord::class));
        $this->assertTrue($user->can('create', PartnerRecord::class));
        $this->assertTrue($user->can('view', $partner));
        $this->assertTrue($user->can('update', $partner));
        $this->assertFalse($user->can('delete', $partner));
    }

    public function test_user_cannot_view_partner_from_another_tenant(): void
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
            'ViewAny:PartnerRecord',
            'View:PartnerRecord',
        ]);

        $partner = PartnerRecord::query()->create([
            'tenant_id' => $otherTenant->id,
            'person_type' => 'company',
            'name' => 'FORNECEDOR OUTRO TENANT LTDA',
            'status' => 'active',
        ]);

        $this->assertFalse($user->can('view', $partner));
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

        $role = Role::findOrCreate('partner_manager_test', $guard);
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
