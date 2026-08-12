<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Seeders;

use Database\Seeders\VanguardAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class VanguardEmployeeFacialPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const PERMISSIONS = [
        'ManageFacialPhoto:EmployeeRecord',
        'ReprocessFacialPhotoDerivative:EmployeeRecord',
    ];

    public function test_employee_facial_permissions_follow_least_privilege_roles(): void
    {
        $this->seed(
            VanguardAccessSeeder::class
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            self::assertSame(
                1,
                Permission::query()
                    ->where(
                        'name',
                        $permission
                    )
                    ->where(
                        'guard_name',
                        'web'
                    )
                    ->count()
            );

            foreach (
                [
                    'super_admin',
                    'admin',
                    'manager',
                ] as $roleName
            ) {
                self::assertTrue(
                    Role::findByName(
                        $roleName,
                        'web'
                    )->hasPermissionTo(
                        $permission
                    ),
                    sprintf(
                        'O papel %s deveria possuir %s.',
                        $roleName,
                        $permission
                    )
                );
            }

            foreach (
                [
                    'operator',
                    'viewer',
                    'panel_user',
                ] as $roleName
            ) {
                self::assertFalse(
                    Role::findByName(
                        $roleName,
                        'web'
                    )->hasPermissionTo(
                        $permission
                    ),
                    sprintf(
                        'O papel %s não deveria possuir %s.',
                        $roleName,
                        $permission
                    )
                );
            }
        }
    }
}
