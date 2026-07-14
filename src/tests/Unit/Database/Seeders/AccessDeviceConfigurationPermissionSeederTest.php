<?php

namespace Tests\Unit\Database\Seeders;

use Database\Seeders\VanguardAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessDeviceConfigurationPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_configuration_read_permission_only_to_administrative_roles(): void
    {
        $this->seed(
            VanguardAccessSeeder::class
        );

        $permission =
            'ReadConfiguration:AccessDeviceRecord';

        $this->assertTrue(
            Role::findByName(
                'super_admin',
                'web'
            )->hasPermissionTo($permission)
        );

        $this->assertTrue(
            Role::findByName(
                'admin',
                'web'
            )->hasPermissionTo($permission)
        );

        $this->assertFalse(
            Role::findByName(
                'manager',
                'web'
            )->hasPermissionTo($permission)
        );

        $this->assertFalse(
            Role::findByName(
                'operator',
                'web'
            )->hasPermissionTo($permission)
        );

        $this->assertFalse(
            Role::findByName(
                'viewer',
                'web'
            )->hasPermissionTo($permission)
        );
    }
}
