<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantMembershipRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_relates_tenants_users_and_organizations(): void
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'AGRONORTE',
            'status' => 'active',
        ]);

        $user = User::factory()->create();

        $tenant->users()->attach($user->id, [
            'role' => 'admin',
            'is_owner' => true,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'AGRONORTE NUTRICAO ANIMAL LTDA',
        ]);

        $membership = TenantMembershipRecord::query()->first();

        $this->assertNotNull($membership);
        $this->assertSame($tenant->id, $membership->tenant_id);
        $this->assertSame($user->id, $membership->user_id);
        $this->assertTrue($membership->is_owner);

        $this->assertTrue($tenant->users()->whereKey($user->id)->exists());
        $this->assertTrue($user->tenants()->where('tenants.id', $tenant->id)->exists());

        $this->assertSame($tenant->id, $organization->tenant->id);
        $this->assertTrue($tenant->organizations()->whereKey($organization->id)->exists());
    }
}
