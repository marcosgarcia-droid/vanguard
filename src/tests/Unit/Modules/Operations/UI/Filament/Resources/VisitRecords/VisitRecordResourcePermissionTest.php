<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitRecords;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\VisitRecordResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisitRecordResourcePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_can_only_view_visits(): void
    {
        $context = $this->context();

        $user = $this->userWithPermissions([
            'ViewAny:VisitRecord',
            'View:VisitRecord',
        ]);

        $this->allowOrganization(
            $user,
            $context['organization']
        );

        $this->actingAs($user);

        $this->assertTrue(
            VisitRecordResource::canViewAny()
        );

        $this->assertTrue(
            VisitRecordResource::canView(
                $context['visit']
            )
        );

        $this->assertFalse(
            VisitRecordResource::canCreate()
        );

        $this->assertFalse(
            VisitRecordResource::canEdit(
                $context['visit']
            )
        );

        $this->assertFalse(
            VisitRecordResource::canDelete(
                $context['visit']
            )
        );
    }

    public function test_operator_can_schedule_but_cannot_edit_or_delete_directly(): void
    {
        $context = $this->context();

        $user = $this->userWithPermissions([
            'ViewAny:VisitRecord',
            'View:VisitRecord',
            'Create:VisitRecord',
            'Update:VisitRecord',
            'Delete:VisitRecord',
        ]);

        $this->allowOrganization(
            $user,
            $context['organization']
        );

        $this->actingAs($user);

        $this->assertTrue(
            VisitRecordResource::canCreate()
        );

        $this->assertTrue(
            VisitRecordResource::canView(
                $context['visit']
            )
        );

        $this->assertFalse(
            VisitRecordResource::canEdit(
                $context['visit']
            )
        );

        $this->assertFalse(
            VisitRecordResource::canDelete(
                $context['visit']
            )
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     visit: VisitRecord
     * }
     */
    private function context(): array
    {
        app(TenantContext::class)->clearSelectedTenant();

        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO DEMONSTRAÇÃO',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE DEMONSTRAÇÃO LTDA',
            'display_name' => 'UNIDADE DEMONSTRAÇÃO',
            'unit_code' => 'DEM-01',
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE DEMONSTRAÇÃO',
            'status' => VisitorStatus::Active,
        ]);

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => VisitStatus::Scheduled,
            'purpose' => 'VISITA OPERACIONAL',
            'expected_start_at' => now()->addHour(),
        ]);

        return compact(
            'tenant',
            'organization',
            'visit'
        );
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(
        array $permissions
    ): User {
        $role = Role::findOrCreate(
            'visit_test_'.Str::random(8),
            'web'
        );

        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user;
    }

    private function allowOrganization(
        User $user,
        OrganizationRecord $organization
    ): void {
        $user->organizations()->attach(
            $organization->id,
            [
                'role' => 'operator',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        app(TenantContext::class)
            ->initializeForUser($user);
    }
}
