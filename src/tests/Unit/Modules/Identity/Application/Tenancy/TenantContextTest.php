<?php

namespace Tests\Unit\Modules\Identity\Application\Tenancy;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_no_organizations_without_a_user(): void
    {
        $tenant = $this->tenant('AGRONORTE');

        $this->organization($tenant, 'AGRONORTE NUTRICAO ANIMAL LTDA');

        $query = app(TenantContext::class)
            ->applyOrganizationScope(OrganizationRecord::query(), null);

        $this->assertSame(0, $query->count());
    }

    public function test_super_admin_without_selected_tenant_can_see_all_organizations(): void
    {
        $firstTenant = $this->tenant('AGRONORTE');
        $secondTenant = $this->tenant('OUTRO GRUPO');

        $user = User::factory()->create();

        Role::findOrCreate('super_admin', 'web');
        $user->assignRole('super_admin');

        $this->organization($firstTenant, 'AGRONORTE NUTRICAO ANIMAL LTDA');
        $this->organization($secondTenant, 'OUTRA EMPRESA LTDA');

        $query = app(TenantContext::class)
            ->applyOrganizationScope(OrganizationRecord::query(), $user);

        $this->assertNull(app(TenantContext::class)->currentTenantIdForUser($user));
        $this->assertSame(2, $query->count());
    }

    public function test_super_admin_with_selected_tenant_sees_only_that_tenant(): void
    {
        $firstTenant = $this->tenant('AGRONORTE');
        $secondTenant = $this->tenant('OUTRO GRUPO');

        $user = User::factory()->create();

        Role::findOrCreate('super_admin', 'web');
        $user->assignRole('super_admin');

        $this->organization($firstTenant, 'AGRONORTE NUTRICAO ANIMAL LTDA');
        $this->organization($secondTenant, 'OUTRA EMPRESA LTDA');

        $context = app(TenantContext::class);

        $this->assertTrue($context->selectTenantForUser($user, $firstTenant));
        $this->assertSame($firstTenant->id, $context->currentTenantIdForUser($user));

        $query = $context->applyOrganizationScope(OrganizationRecord::query(), $user);

        $this->assertSame(1, $query->count());
        $this->assertSame('AGRONORTE NUTRICAO ANIMAL LTDA', $query->first()?->legal_name);
    }

    public function test_regular_user_only_sees_organizations_from_active_tenants(): void
    {
        $allowedTenant = $this->tenant('AGRONORTE');
        $blockedTenant = $this->tenant('OUTRO GRUPO');

        $user = User::factory()->create();

        $user->tenants()->attach($allowedTenant->id, [
            'role' => 'operator',
            'is_owner' => false,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->organization($allowedTenant, 'AGRONORTE NUTRICAO ANIMAL LTDA');
        $this->organization($blockedTenant, 'OUTRA EMPRESA LTDA');

        $query = app(TenantContext::class)
            ->applyOrganizationScope(OrganizationRecord::query(), $user);

        $this->assertSame(1, $query->count());
        $this->assertSame('AGRONORTE NUTRICAO ANIMAL LTDA', $query->first()?->legal_name);
    }

    public function test_regular_user_cannot_select_unrelated_tenant(): void
    {
        $allowedTenant = $this->tenant('AGRONORTE');
        $blockedTenant = $this->tenant('OUTRO GRUPO');

        $user = User::factory()->create();

        $user->tenants()->attach($allowedTenant->id, [
            'role' => 'operator',
            'is_owner' => false,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $context = app(TenantContext::class);

        $this->assertFalse($context->selectTenantForUser($user, $blockedTenant));
        $this->assertSame($allowedTenant->id, $context->currentTenantIdForUser($user));
    }

    public function test_selected_tenant_can_be_cleared(): void
    {
        $tenant = $this->tenant('AGRONORTE');
        $user = User::factory()->create();

        Role::findOrCreate('super_admin', 'web');
        $user->assignRole('super_admin');

        $context = app(TenantContext::class);

        $this->assertTrue($context->selectTenantForUser($user, $tenant));
        $this->assertSame($tenant->id, $context->selectedTenantId());

        $context->clearSelectedTenant();

        $this->assertNull($context->selectedTenantId());
        $this->assertNull($context->currentTenantIdForUser($user));
    }

    public function test_super_admin_available_tenants_include_all_active_tenants(): void
    {
        $activeTenant = $this->tenant('AGRONORTE');
        $inactiveTenant = $this->tenant('INATIVO', 'inactive');

        $user = User::factory()->create();

        Role::findOrCreate('super_admin', 'web');
        $user->assignRole('super_admin');

        $tenantNames = app(TenantContext::class)
            ->availableTenantsForUser($user)
            ->pluck('name')
            ->all();

        $this->assertContains($activeTenant->name, $tenantNames);
        $this->assertNotContains($inactiveTenant->name, $tenantNames);
    }

    public function test_regular_user_available_tenants_include_only_active_memberships(): void
    {
        $activeTenant = $this->tenant('AGRONORTE');
        $inactiveTenant = $this->tenant('INATIVO', 'inactive');
        $unrelatedTenant = $this->tenant('OUTRO GRUPO');

        $user = User::factory()->create();

        $user->tenants()->attach($activeTenant->id, [
            'role' => 'operator',
            'is_owner' => false,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $user->tenants()->attach($inactiveTenant->id, [
            'role' => 'operator',
            'is_owner' => false,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $tenantNames = app(TenantContext::class)
            ->availableTenantsForUser($user)
            ->pluck('name')
            ->all();

        $this->assertContains($activeTenant->name, $tenantNames);
        $this->assertNotContains($inactiveTenant->name, $tenantNames);
        $this->assertNotContains($unrelatedTenant->name, $tenantNames);
    }

    private function tenant(string $name, string $status = 'active'): TenantRecord
    {
        return TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'status' => $status,
        ]);
    }

    private function organization(TenantRecord $tenant, string $legalName): OrganizationRecord
    {
        return OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => $legalName,
        ]);
    }
}
