<?php

namespace Tests\Unit\Database\Seeders;

use Database\Seeders\VanguardAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessEventPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_event_permissions_to_operational_roles(): void
    {
        $this->seed(
            VanguardAccessSeeder::class
        );

        $permissions = [
            'ViewAny:AccessEventRecord',
            'View:AccessEventRecord',
            'ReprocessFlow:AccessEventRecord',
            'AssociateManually:AccessEventRecord',
        ];

        foreach ([
            'super_admin',
            'admin',
            'manager',
            'operator',
        ] as $roleName) {
            $role = Role::findByName(
                $roleName,
                'web'
            );

            foreach ($permissions as $permission) {
                $this->assertTrue(
                    $role->hasPermissionTo(
                        $permission
                    ),
                    "{$roleName} deveria possuir {$permission}."
                );
            }
        }

        $viewer = Role::findByName(
            'viewer',
            'web'
        );

        foreach ($permissions as $permission) {
            $this->assertFalse(
                $viewer->hasPermissionTo(
                    $permission
                ),
                "viewer não deveria possuir {$permission}."
            );
        }
    }
}
