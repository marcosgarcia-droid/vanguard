<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisitorRecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_manage_visitor_in_allowed_unit_without_direct_tenant_membership(): void
    {
        app(TenantContext::class)->clearSelectedTenant();

        $tenant = $this->createTenant('GRUPO PERMITIDO');

        $organization = $this->createOrganization(
            $tenant,
            'UNIDADE PERMITIDA',
            'PER-01'
        );

        $user = $this->createUserWithPermissions([
            'ViewAny:VisitorRecord',
            'View:VisitorRecord',
            'Create:VisitorRecord',
            'Update:VisitorRecord',
        ]);

        $user->organizations()->attach($organization->id, [
            'role' => 'operator',
            'is_active' => true,
            'granted_at' => now(),
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'Visitante Demonstração',
            'status' => VisitorStatus::Active,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue(
            $user->can('viewAny', VisitorRecord::class)
        );

        $this->assertTrue(
            $user->can('create', VisitorRecord::class)
        );

        $this->assertTrue(
            $user->can('view', $visitor)
        );

        $this->assertTrue(
            $user->can('update', $visitor)
        );

        $this->assertFalse(
            $user->can('delete', $visitor)
        );

        $this->assertSame(
            $tenant->id,
            app(TenantContext::class)->currentTenantIdForUser($user)
        );
    }

    public function test_user_cannot_access_visitor_from_unallowed_unit_in_same_group(): void
    {
        app(TenantContext::class)->clearSelectedTenant();

        $tenant = $this->createTenant('GRUPO DEMONSTRAÇÃO');

        $allowedOrganization = $this->createOrganization(
            $tenant,
            'UNIDADE PERMITIDA',
            'PER-01'
        );

        $otherOrganization = $this->createOrganization(
            $tenant,
            'UNIDADE NÃO PERMITIDA',
            'BLQ-01'
        );

        $user = $this->createUserWithPermissions([
            'ViewAny:VisitorRecord',
            'View:VisitorRecord',
            'Update:VisitorRecord',
        ]);

        $user->organizations()->attach($allowedOrganization->id, [
            'role' => 'operator',
            'is_active' => true,
            'granted_at' => now(),
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $otherOrganization->id,
            'full_name' => 'Visitante Outra Unidade',
            'status' => VisitorStatus::Active,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue(
            $user->can('viewAny', VisitorRecord::class)
        );

        $this->assertFalse(
            $user->can('view', $visitor)
        );

        $this->assertFalse(
            $user->can('update', $visitor)
        );
    }

    private function createTenant(string $name): TenantRecord
    {
        return TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function createOrganization(
        TenantRecord $tenant,
        string $name,
        string $code
    ): OrganizationRecord {
        return OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => "{$name} LTDA",
            'display_name' => $name,
            'unit_code' => $code,
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function createUserWithPermissions(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate(
            'visitor_operator_test',
            'web'
        );

        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
