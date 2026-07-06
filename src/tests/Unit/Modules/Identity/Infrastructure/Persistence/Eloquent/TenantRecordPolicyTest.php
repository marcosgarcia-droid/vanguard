<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantRecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_manage_tenants(): void
    {
        $user = User::factory()->create();
        $tenant = TenantRecord::query()->create([
            'name' => 'AGRONORTE',
            'status' => 'active',
        ]);

        $this->assertFalse(Gate::forUser($user)->allows('viewAny', TenantRecord::class));
        $this->assertFalse(Gate::forUser($user)->allows('view', $tenant));
        $this->assertFalse(Gate::forUser($user)->allows('create', TenantRecord::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', $tenant));
    }

    public function test_super_admin_can_manage_tenants(): void
    {
        $user = User::factory()->create();

        Role::findOrCreate('super_admin', 'web');
        $user->assignRole('super_admin');

        $tenant = TenantRecord::query()->create([
            'name' => 'AGRONORTE',
            'status' => 'active',
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', TenantRecord::class));
        $this->assertTrue(Gate::forUser($user)->allows('view', $tenant));
        $this->assertTrue(Gate::forUser($user)->allows('create', TenantRecord::class));
        $this->assertTrue(Gate::forUser($user)->allows('update', $tenant));
    }

    public function test_tenant_record_generates_uuid_when_created(): void
    {
        $tenant = TenantRecord::query()->create([
            'name' => 'AGRONORTE',
            'status' => 'active',
        ]);

        $this->assertNotEmpty($tenant->id);
    }
}
