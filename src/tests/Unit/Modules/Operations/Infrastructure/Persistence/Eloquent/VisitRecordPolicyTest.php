<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisitRecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_manage_visit_in_allowed_unit(): void
    {
        app(TenantContext::class)->clearSelectedTenant();

        $tenant = $this->createTenant();

        $organization = $this->createOrganization(
            $tenant,
            'UNIDADE PERMITIDA',
            'PER-01'
        );

        $user = $this->createUserWithPermissions([
            'ViewAny:VisitRecord',
            'View:VisitRecord',
            'Create:VisitRecord',
            'Update:VisitRecord',
        ]);

        $user->organizations()->attach($organization->id, [
            'role' => 'operator',
            'is_active' => true,
            'granted_at' => now(),
        ]);

        $visitor = $this->createVisitor(
            $tenant,
            $organization
        );

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => VisitStatus::Scheduled,
            'purpose' => 'Visita técnica',
            'expected_start_at' => now()->addHour(),
            'expected_end_at' => now()->addHours(2),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue(
            $user->can('viewAny', VisitRecord::class)
        );

        $this->assertTrue(
            $user->can('create', VisitRecord::class)
        );

        $this->assertTrue(
            $user->can('view', $visit)
        );

        $this->assertTrue(
            $user->can('update', $visit)
        );

        $this->assertFalse(
            $user->can('delete', $visit)
        );
    }

    public function test_user_cannot_access_visit_from_unallowed_unit(): void
    {
        app(TenantContext::class)->clearSelectedTenant();

        $tenant = $this->createTenant();

        $allowedOrganization = $this->createOrganization(
            $tenant,
            'UNIDADE PERMITIDA',
            'PER-01'
        );

        $otherOrganization = $this->createOrganization(
            $tenant,
            'UNIDADE NÃO PERMITIDA',
            'BLQ-01'
        );

        $user = $this->createUserWithPermissions([
            'ViewAny:VisitRecord',
            'View:VisitRecord',
            'Update:VisitRecord',
        ]);

        $user->organizations()->attach($allowedOrganization->id, [
            'role' => 'operator',
            'is_active' => true,
            'granted_at' => now(),
        ]);

        $visitor = $this->createVisitor(
            $tenant,
            $otherOrganization
        );

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $otherOrganization->id,
            'visitor_id' => $visitor->id,
            'status' => VisitStatus::Scheduled,
            'purpose' => 'Visita em outra unidade',
            'expected_start_at' => now()->addHour(),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse(
            $user->can('view', $visit)
        );

        $this->assertFalse(
            $user->can('update', $visit)
        );
    }

    private function createTenant(): TenantRecord
    {
        return TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO DEMONSTRAÇÃO',
            'status' => 'active',
        ]);
    }

    private function createOrganization(
        TenantRecord $tenant,
        string $name,
        string $code
    ): OrganizationRecord {
        return OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => "{$name} LTDA",
            'display_name' => $name,
            'unit_code' => $code,
        ]);
    }

    private function createVisitor(
        TenantRecord $tenant,
        OrganizationRecord $organization
    ): VisitorRecord {
        return VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'Visitante Demonstração',
            'status' => VisitorStatus::Active,
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function createUserWithPermissions(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate(
            'visit_operator_test',
            'web'
        );

        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
