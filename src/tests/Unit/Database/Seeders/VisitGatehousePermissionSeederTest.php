<?php

namespace Tests\Unit\Database\Seeders;

use Database\Seeders\VanguardAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VisitGatehousePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_gatehouse_operation_only_to_the_operator_role(): void
    {
        $this->seed(
            VanguardAccessSeeder::class
        );

        $permission = Permission::findByName(
            'OperateGatehouse:VisitRecord',
            'web'
        );

        foreach ([
            'super_admin',
            'operator',
        ] as $roleName) {
            $this->assertTrue(
                Role::findByName(
                    $roleName,
                    'web'
                )->hasPermissionTo($permission),
                "{$roleName} deveria possuir a permissão de portaria."
            );
        }

        foreach ([
            'panel_user',
            'admin',
            'manager',
            'viewer',
        ] as $roleName) {
            $this->assertFalse(
                Role::findByName(
                    $roleName,
                    'web'
                )->hasPermissionTo($permission),
                "{$roleName} não deveria possuir a permissão de portaria."
            );
        }
    }
}
