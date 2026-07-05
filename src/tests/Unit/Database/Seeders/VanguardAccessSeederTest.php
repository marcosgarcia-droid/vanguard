<?php

namespace Tests\Unit\Database\Seeders;

use App\Models\User;
use Database\Seeders\VanguardAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VanguardAccessSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_access_roles_test_users_and_tenant_memberships(): void
    {
        $this->seed(VanguardAccessSeeder::class);

        foreach (['super_admin', 'panel_user', 'admin', 'manager', 'operator', 'viewer'] as $role) {
            $this->assertTrue(Role::query()->where('name', $role)->exists());
        }

        $admin = User::query()->where('email', 'admin@vanguard.test')->firstOrFail();
        $manager = User::query()->where('email', 'gestor@vanguard.test')->firstOrFail();
        $operator = User::query()->where('email', 'operador@vanguard.test')->firstOrFail();
        $viewer = User::query()->where('email', 'visualizador@vanguard.test')->firstOrFail();

        $this->assertTrue($admin->hasRole('panel_user'));
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertFalse($admin->hasRole('super_admin'));

        $this->assertTrue($manager->hasRole('panel_user'));
        $this->assertTrue($manager->hasRole('manager'));

        $this->assertTrue($operator->hasRole('panel_user'));
        $this->assertTrue($operator->hasRole('operator'));

        $this->assertTrue($viewer->hasRole('panel_user'));
        $this->assertTrue($viewer->hasRole('viewer'));

        $this->assertDatabaseHas('tenants', [
            'name' => 'AGRONORTE',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('tenant_user', [
            'user_id' => $admin->id,
            'role' => 'admin',
            'is_owner' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('tenant_user', [
            'user_id' => $viewer->id,
            'role' => 'viewer',
            'is_owner' => false,
            'is_active' => true,
        ]);
    }
}
