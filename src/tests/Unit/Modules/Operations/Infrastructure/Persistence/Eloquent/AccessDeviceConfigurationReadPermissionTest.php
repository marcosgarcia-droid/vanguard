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
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccessDeviceConfigurationReadPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_read_configuration_from_allowed_unit(): void
    {
        [
            $organization,
            $device,
        ] = $this->createContext();

        $user = User::factory()->create();

        Permission::findOrCreate(
            'ReadConfiguration:AccessDeviceRecord',
            'web'
        );

        $user->givePermissionTo(
            'ReadConfiguration:AccessDeviceRecord'
        );

        $user->organizations()->attach(
            $organization->id,
            [
                'role' => 'admin',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        app(TenantContext::class)
            ->clearSelectedTenant();

        $this->assertTrue(
            $user->can(
                'readConfiguration',
                $device
            )
        );
    }

    public function test_view_permission_alone_does_not_allow_a_new_device_read(): void
    {
        [
            $organization,
            $device,
        ] = $this->createContext();

        $user = User::factory()->create();

        Permission::findOrCreate(
            'View:AccessDeviceRecord',
            'web'
        );

        $user->givePermissionTo(
            'View:AccessDeviceRecord'
        );

        $user->organizations()->attach(
            $organization->id,
            [
                'role' => 'manager',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        app(TenantContext::class)
            ->clearSelectedTenant();

        $this->assertFalse(
            $user->can(
                'readConfiguration',
                $device
            )
        );
    }

    /**
     * @return array{
     *     0: OrganizationRecord,
     *     1: AccessDeviceRecord
     * }
     */
    private function createContext(): array
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO DEMONSTRAÇÃO',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE DEMONSTRAÇÃO LTDA',
                'display_name' => 'UNIDADE DEMONSTRAÇÃO',
                'unit_code' => 'DEM-01',
            ]);

        $device =
            AccessDeviceRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'code' => 'FAC-ENT-01',
                'name' => 'Facial entrada 01',
                'provider' => 'intelbras',
                'direction' => AccessDeviceDirection::Entry,
                'status' => AccessDeviceStatus::Active,
            ]);

        return [
            $organization,
            $device,
        ];
    }
}
