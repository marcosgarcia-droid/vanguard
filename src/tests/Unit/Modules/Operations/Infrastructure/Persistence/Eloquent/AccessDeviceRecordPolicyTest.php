<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccessDeviceRecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_device_in_allowed_unit_but_cannot_delete_it(): void
    {
        app(TenantContext::class)->clearSelectedTenant();

        $tenant = $this->createTenant();

        $organization = $this->createOrganization(
            $tenant,
            'UNIDADE PERMITIDA',
            'PER-01'
        );

        $user = $this->createUserWithPermissions([
            'ViewAny:AccessDeviceRecord',
            'View:AccessDeviceRecord',
            'Create:AccessDeviceRecord',
            'Update:AccessDeviceRecord',
            'Delete:AccessDeviceRecord',
        ]);

        $user->organizations()->attach(
            $organization->id,
            [
                'role' => 'admin',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        $device = $this->createDevice(
            $tenant,
            $organization
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->assertTrue(
            $user->can(
                'viewAny',
                AccessDeviceRecord::class
            )
        );

        $this->assertTrue(
            $user->can(
                'create',
                AccessDeviceRecord::class
            )
        );

        $this->assertTrue(
            $user->can('view', $device)
        );

        $this->assertTrue(
            $user->can('update', $device)
        );

        $this->assertFalse(
            $user->can('delete', $device)
        );
    }

    public function test_user_cannot_access_device_from_unallowed_unit(): void
    {
        app(TenantContext::class)->clearSelectedTenant();

        $tenant = $this->createTenant();

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
            'ViewAny:AccessDeviceRecord',
            'View:AccessDeviceRecord',
            'Update:AccessDeviceRecord',
        ]);

        $user->organizations()->attach(
            $allowedOrganization->id,
            [
                'role' => 'manager',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        $device = $this->createDevice(
            $tenant,
            $otherOrganization
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->assertTrue(
            $user->can(
                'viewAny',
                AccessDeviceRecord::class
            )
        );

        $this->assertFalse(
            $user->can('view', $device)
        );

        $this->assertFalse(
            $user->can('update', $device)
        );
    }

    private function createTenant(): TenantRecord
    {
        return TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO DEMONSTRAÇÃO',
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

    private function createDevice(
        TenantRecord $tenant,
        OrganizationRecord $organization
    ): AccessDeviceRecord {
        return AccessDeviceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => 'FAC-ENT-01',
            'name' => 'Facial entrada 01',
            'provider' => 'intelbras',
            'direction' => AccessDeviceDirection::Entry,
            'status' => AccessDeviceStatus::Active,
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function createUserWithPermissions(
        array $permissions
    ): User {
        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        $role = Role::findOrCreate(
            'access_device_admin_test',
            'web'
        );

        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user;
    }
}
