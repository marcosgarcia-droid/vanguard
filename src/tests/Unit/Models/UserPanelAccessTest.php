<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_allowed_role_cannot_access_panel(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel($this->panel()));
    }

    public function test_super_admin_can_access_panel(): void
    {
        $user = User::factory()->create();

        Role::query()->create([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $user->assignRole('super_admin');

        $this->assertTrue($user->canAccessPanel($this->panel()));
    }

    public function test_panel_user_can_access_panel(): void
    {
        $user = User::factory()->create();

        Role::query()->create([
            'name' => 'panel_user',
            'guard_name' => 'web',
        ]);

        $user->assignRole('panel_user');

        $this->assertTrue($user->canAccessPanel($this->panel()));
    }

    private function panel(): Panel
    {
        return Mockery::mock(Panel::class);
    }
}
