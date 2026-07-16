<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccessEventRecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_view_and_reprocess_event_from_allowed_unit_but_cannot_modify_it(): void
    {
        app(TenantContext::class)
            ->clearSelectedTenant();

        [$tenant, $organization] =
            $this->createScope(
                'UNIDADE PERMITIDA',
                'PER-01'
            );

        $user = $this->createUserWithPermissions([
            'ViewAny:AccessEventRecord',
            'View:AccessEventRecord',
            'ReprocessFlow:AccessEventRecord',
        ]);

        $this->allowOrganization(
            $user,
            $organization
        );

        $event = $this->createEvent(
            $tenant,
            $organization
        );

        $this->assertTrue(
            $user->can(
                'viewAny',
                AccessEventRecord::class
            )
        );

        $this->assertTrue(
            $user->can('view', $event)
        );

        $this->assertTrue(
            $user->can(
                'reprocessFlow',
                $event
            )
        );

        $this->assertFalse(
            $user->can(
                'create',
                AccessEventRecord::class
            )
        );

        $this->assertFalse(
            $user->can('update', $event)
        );

        $this->assertFalse(
            $user->can('delete', $event)
        );
    }

    public function test_view_permission_does_not_grant_reprocessing(): void
    {
        app(TenantContext::class)
            ->clearSelectedTenant();

        [$tenant, $organization] =
            $this->createScope(
                'UNIDADE VISUALIZAÇÃO',
                'VIS-01'
            );

        $user = $this->createUserWithPermissions([
            'ViewAny:AccessEventRecord',
            'View:AccessEventRecord',
        ]);

        $this->allowOrganization(
            $user,
            $organization
        );

        $event = $this->createEvent(
            $tenant,
            $organization
        );

        $this->assertTrue(
            $user->can('view', $event)
        );

        $this->assertFalse(
            $user->can(
                'reprocessFlow',
                $event
            )
        );
    }

    public function test_user_cannot_access_event_from_unallowed_unit(): void
    {
        app(TenantContext::class)
            ->clearSelectedTenant();

        [$tenant, $allowedOrganization] =
            $this->createScope(
                'UNIDADE PERMITIDA',
                'PER-01'
            );

        $otherOrganization =
            $this->createOrganization(
                $tenant,
                'UNIDADE NÃO PERMITIDA',
                'BLQ-01'
            );

        $user = $this->createUserWithPermissions([
            'ViewAny:AccessEventRecord',
            'View:AccessEventRecord',
            'ReprocessFlow:AccessEventRecord',
        ]);

        $this->allowOrganization(
            $user,
            $allowedOrganization
        );

        $event = $this->createEvent(
            $tenant,
            $otherOrganization
        );

        $this->assertFalse(
            $user->can('view', $event)
        );

        $this->assertFalse(
            $user->can(
                'reprocessFlow',
                $event
            )
        );
    }

    public function test_super_admin_can_view_and_reprocess_but_cannot_modify_events(): void
    {
        [$tenant, $organization] =
            $this->createScope(
                'UNIDADE GLOBAL',
                'GLO-01'
            );

        $role = Role::findOrCreate(
            'super_admin',
            'web'
        );

        $user = User::factory()->create();
        $user->assignRole($role);

        $event = $this->createEvent(
            $tenant,
            $organization
        );

        $this->assertTrue(
            $user->can(
                'viewAny',
                AccessEventRecord::class
            )
        );

        $this->assertTrue(
            $user->can('view', $event)
        );

        $this->assertTrue(
            $user->can(
                'reprocessFlow',
                $event
            )
        );

        $this->assertFalse(
            $user->can(
                'create',
                AccessEventRecord::class
            )
        );

        $this->assertFalse(
            $user->can('update', $event)
        );

        $this->assertFalse(
            $user->can('delete', $event)
        );
    }

    /**
     * @return array{
     *     0: TenantRecord,
     *     1: OrganizationRecord
     * }
     */
    private function createScope(
        string $organizationName,
        string $organizationCode
    ): array {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO EVENTOS',
            'status' => 'active',
        ]);

        return [
            $tenant,
            $this->createOrganization(
                $tenant,
                $organizationName,
                $organizationCode
            ),
        ];
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

    private function createEvent(
        TenantRecord $tenant,
        OrganizationRecord $organization
    ): AccessEventRecord {
        $device = AccessDeviceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => 'FAC-EVT-01',
            'name' => 'Facial eventos',
            'provider' => 'simulator',
            'direction' => AccessDeviceDirection::Entry,
            'status' => AccessDeviceStatus::Active,
        ]);

        return AccessEventRecord::query()->create([
            'access_device_id' => $device->id,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'external_event_id' => 'event-'.Str::uuid(),
            'event_type' => 'face_recognition',
            'direction' => AccessEventDirection::Entry,
            'occurred_at' => now(),
            'status' => AccessEventStatus::Received,
        ]);
    }

    private function allowOrganization(
        User $user,
        OrganizationRecord $organization
    ): void {
        $user->organizations()->attach(
            $organization->id,
            [
                'role' => 'operator',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );
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
            'access_event_operator_test',
            'web'
        );

        $role->syncPermissions(
            $permissions
        );

        $user = User::factory()->create();
        $user->assignRole($role);

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user;
    }
}
