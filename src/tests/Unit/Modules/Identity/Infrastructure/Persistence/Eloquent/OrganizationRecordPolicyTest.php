<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecordPolicy;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationRecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_can_view_but_cannot_create_update_or_delete(): void
    {
        $tenant = $this->tenant();
        $user = $this->userWithPermissions([
            'ViewAny:OrganizationRecord',
            'View:OrganizationRecord',
        ], $tenant);

        $organization = $this->organization($tenant);
        $this->grantOrganizationAccess($user, $organization);

        $policy = new OrganizationRecordPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $organization));
        $this->assertFalse($policy->create($user));
        $this->assertFalse($policy->update($user, $organization));
        $this->assertFalse($policy->delete($user, $organization));
    }

    public function test_operator_can_create_and_update_but_cannot_delete(): void
    {
        $tenant = $this->tenant();
        $user = $this->userWithPermissions([
            'ViewAny:OrganizationRecord',
            'View:OrganizationRecord',
            'Create:OrganizationRecord',
            'Update:OrganizationRecord',
        ], $tenant);

        $organization = $this->organization($tenant);
        $this->grantOrganizationAccess($user, $organization);

        $policy = new OrganizationRecordPolicy;

        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $organization));
        $this->assertFalse($policy->delete($user, $organization));
    }

    public function test_user_cannot_manage_organization_from_another_tenant(): void
    {
        $allowedTenant = $this->tenant('AGRONORTE');
        $blockedTenant = $this->tenant('OUTRO GRUPO');

        $user = $this->userWithPermissions([
            'ViewAny:OrganizationRecord',
            'View:OrganizationRecord',
            'Update:OrganizationRecord',
        ], $allowedTenant);

        $organization = $this->organization($blockedTenant);

        $policy = new OrganizationRecordPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertFalse($policy->view($user, $organization));
        $this->assertFalse($policy->update($user, $organization));
    }

    public function test_super_admin_can_manage_everything(): void
    {
        $tenant = $this->tenant();
        $organization = $this->organization($tenant);

        $user = User::factory()->create();

        Role::findOrCreate('super_admin', 'web');
        $user->assignRole('super_admin');

        $policy = new OrganizationRecordPolicy;

        $this->assertTrue($policy->before($user, 'anything'));
        $this->assertTrue(Gate::forUser($user)->allows('viewAny', OrganizationRecord::class));
        $this->assertTrue(Gate::forUser($user)->allows('view', $organization));
        $this->assertTrue(Gate::forUser($user)->allows('create', OrganizationRecord::class));
        $this->assertTrue(Gate::forUser($user)->allows('update', $organization));
        $this->assertTrue(Gate::forUser($user)->allows('delete', $organization));
    }

    private function grantOrganizationAccess(User $user, OrganizationRecord $organization): void
    {
        $user->organizations()->attach($organization->id, [
            'role' => 'member',
            'is_active' => true,
            'granted_at' => now(),
        ]);
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

    private function organization(TenantRecord $tenant): OrganizationRecord
    {
        return OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'AGRONORTE NUTRICAO ANIMAL LTDA',
        ]);
    }
}
