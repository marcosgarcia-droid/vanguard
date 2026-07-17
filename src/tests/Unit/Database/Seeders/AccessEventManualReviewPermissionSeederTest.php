<?php

namespace Tests\Unit\Database\Seeders;

use Database\Seeders\VanguardAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessEventManualReviewPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_manual_review_resolution_permission(): void
    {
        $this->seed(
            VanguardAccessSeeder::class
        );

        $permission = Permission::findByName(
            'ResolveManualReview:AccessEventRecord',
            'web'
        );

        $this->assertNotNull($permission);

        foreach ([
            'super_admin',
            'admin',
            'manager',
            'operator',
        ] as $roleName) {
            $this->assertTrue(
                Role::findByName(
                    $roleName,
                    'web'
                )->hasPermissionTo($permission),
                "O papel {$roleName} deveria possuir a permissão."
            );
        }

        $viewer = Role::findByName(
            'viewer',
            'web'
        );

        $this->assertFalse(
            $viewer->hasPermissionTo($permission)
        );
    }
}
