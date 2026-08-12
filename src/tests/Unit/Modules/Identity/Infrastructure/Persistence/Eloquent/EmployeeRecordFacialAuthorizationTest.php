<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class EmployeeRecordFacialAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_manager_can_manage_and_reprocess_employee_facial_photo_in_allowed_unit(): void
    {
        [
            $tenant,
            $organization,
            $employee,
        ] = $this->employeeContext(
            'PERMITIDA'
        );

        $user = $this->userWithFacialPermissions();

        $user->tenants()->attach(
            $tenant->id,
            [
                'role' => 'manager',
                'is_owner' => false,
                'is_active' => true,
                'joined_at' => now(),
            ]
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

        self::assertTrue(
            Gate::forUser($user)->allows(
                'manageFacialPhoto',
                $employee
            )
        );

        self::assertTrue(
            Gate::forUser($user)->allows(
                'reprocessFacialPhotoDerivative',
                $employee
            )
        );
    }

    public function test_normal_employee_update_permission_does_not_grant_biometric_management(): void
    {
        [
            $tenant,
            $organization,
            $employee,
        ] = $this->employeeContext(
            'SEM-FACIAL'
        );

        Permission::findOrCreate(
            'Update:EmployeeRecord',
            'web'
        );

        $role = Role::findOrCreate(
            'employee_update_only_test',
            'web'
        );

        $role->syncPermissions([
            'Update:EmployeeRecord',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        $user->tenants()->attach(
            $tenant->id,
            [
                'role' => 'manager',
                'is_owner' => false,
                'is_active' => true,
                'joined_at' => now(),
            ]
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

        self::assertTrue(
            Gate::forUser($user)->allows(
                'update',
                $employee
            )
        );

        self::assertFalse(
            Gate::forUser($user)->allows(
                'manageFacialPhoto',
                $employee
            )
        );

        self::assertFalse(
            Gate::forUser($user)->allows(
                'reprocessFacialPhotoDerivative',
                $employee
            )
        );
    }

    public function test_facial_permission_does_not_bypass_allowed_unit_scope(): void
    {
        $tenant = TenantRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO E1 ESCOPO',
                'status' => 'active',
            ]);

        $allowedOrganization =
            $this->organization(
                $tenant,
                'UNIDADE PERMITIDA',
                'E1-PER'
            );

        $blockedOrganization =
            $this->organization(
                $tenant,
                'UNIDADE BLOQUEADA',
                'E1-BLO'
            );

        $employee = EmployeeRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $blockedOrganization->id,
                'employee_code' => 'E1-BLO-001',
                'full_name' => 'FUNCIONÁRIO OUTRA UNIDADE',
                'employment_type' => 'employee',
                'status' => 'active',
            ]);

        $user = $this->userWithFacialPermissions();

        $user->tenants()->attach(
            $tenant->id,
            [
                'role' => 'manager',
                'is_owner' => false,
                'is_active' => true,
                'joined_at' => now(),
            ]
        );

        $user->organizations()->attach(
            $allowedOrganization->id,
            [
                'role' => 'manager',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        self::assertFalse(
            Gate::forUser($user)->allows(
                'manageFacialPhoto',
                $employee
            )
        );

        self::assertFalse(
            Gate::forUser($user)->allows(
                'reprocessFacialPhotoDerivative',
                $employee
            )
        );
    }

    /**
     * @return array{
     *     TenantRecord,
     *     OrganizationRecord,
     *     EmployeeRecord
     * }
     */
    private function employeeContext(
        string $suffix
    ): array {
        $tenant = TenantRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO E1 '.$suffix,
                'status' => 'active',
            ]);

        $organization = $this->organization(
            $tenant,
            'UNIDADE E1 '.$suffix,
            'E1-'.$suffix
        );

        $employee = EmployeeRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'employee_code' => 'EMP-E1-'.$suffix,
                'full_name' => 'FUNCIONÁRIO E1 '.$suffix,
                'employment_type' => 'employee',
                'status' => 'active',
            ]);

        return [
            $tenant,
            $organization,
            $employee,
        ];
    }

    private function organization(
        TenantRecord $tenant,
        string $name,
        string $code
    ): OrganizationRecord {
        return OrganizationRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => $name.' LTDA',
                'display_name' => $name,
                'unit_code' => $code,
            ]);
    }

    private function userWithFacialPermissions(): User
    {
        foreach (
            [
                'ManageFacialPhoto:EmployeeRecord',
                'ReprocessFacialPhotoDerivative:EmployeeRecord',
            ] as $permission
        ) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        $role = Role::findOrCreate(
            'employee_facial_manager_test',
            'web'
        );

        $role->syncPermissions([
            'ManageFacialPhoto:EmployeeRecord',
            'ReprocessFacialPhotoDerivative:EmployeeRecord',
        ]);

        $user = User::factory()->create();

        $user->assignRole(
            $role
        );

        return $user;
    }
}
