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
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Pages\KanbanVisitRecords;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Pages\ListVisitRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use ReflectionClass;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisitCreationModesTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_actions_use_clear_operational_names(): void
    {
        $filename = (
            new ReflectionClass(ListVisitRecords::class)
        )->getFileName();

        $this->assertIsString($filename);

        $source = file_get_contents($filename);

        $this->assertIsString($source);

        foreach ([
            'CreateAction::make()',
            "->label('Agendar visita')",
            "->tooltip('Agendar uma visita')",
            "CreateAction::make('registerVisitor')",
            "->label('Registrar visitante')",
            "->tooltip('Registrar visitante na portaria')",
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        $this->assertStringNotContainsString(
            "->label('Nova visita')",
            $source
        );
    }

    public function test_only_gatehouse_user_sees_register_visitor_action(): void
    {
        $context = $this->context();

        $manager = $this->userWithPermissions([
            'ViewAny:VisitRecord',
            'Create:VisitRecord',
        ]);

        $operator = $this->userWithPermissions([
            'ViewAny:VisitRecord',
            'Create:VisitRecord',
            'OperateGatehouse:VisitRecord',
        ]);

        $this->allowOrganization(
            $manager,
            $context['organization'],
            'manager'
        );

        $this->actingAs($manager);

        Livewire::test(KanbanVisitRecords::class)
            ->assertActionVisible('create')
            ->assertActionHidden('registerVisitor');

        app(TenantContext::class)->clearSelectedTenant();

        $this->allowOrganization(
            $operator,
            $context['organization'],
            'operator'
        );

        $this->actingAs($operator);

        Livewire::test(KanbanVisitRecords::class)
            ->assertActionVisible('create')
            ->assertActionVisible('registerVisitor');
    }

    public function test_schedule_action_keeps_visit_scheduled_without_arrival(): void
    {
        $context = $this->context();

        $manager = $this->userWithPermissions([
            'ViewAny:VisitRecord',
            'Create:VisitRecord',
        ]);

        $this->allowOrganization(
            $manager,
            $context['organization'],
            'manager'
        );

        $this->actingAs($manager);

        $expectedStart = now()
            ->addHour()
            ->startOfMinute();

        Livewire::test(KanbanVisitRecords::class)
            ->callAction('create', [
                'organization_id' => $context['organization']->id,
                'visitor_id' => $context['visitor']->id,
                'host_employee_id' => null,
                'partner_id' => null,
                'purpose' => 'AGENDAMENTO PROGRAMADO 6B.11',
                'expected_start_at' => $expectedStart
                    ->format('Y-m-d H:i:s'),
                'expected_end_at' => $expectedStart
                    ->copy()
                    ->addHour()
                    ->format('Y-m-d H:i:s'),
            ]);

        $visit = VisitRecord::query()
            ->where(
                'purpose',
                'AGENDAMENTO PROGRAMADO 6B.11'
            )
            ->sole();

        $this->assertSame(
            VisitStatus::Scheduled,
            $visit->status
        );

        $this->assertTrue(
            $visit->expected_start_at?->equalTo(
                $expectedStart
            ) ?? false
        );

        $this->assertNull($visit->arrived_by);
        $this->assertNull($visit->arrived_at);
        $this->assertNull($visit->identity_verified_by);
        $this->assertNull($visit->identity_verified_at);
    }

    public function test_register_visitor_creates_pending_visit_with_arrival_and_identity(): void
    {
        $context = $this->context();

        $operator = $this->userWithPermissions([
            'ViewAny:VisitRecord',
            'Create:VisitRecord',
            'OperateGatehouse:VisitRecord',
        ]);

        $this->allowOrganization(
            $operator,
            $context['organization'],
            'operator'
        );

        $this->actingAs($operator);

        $registeredAt = now()
            ->startOfSecond();

        $this->travelTo($registeredAt);

        Livewire::test(KanbanVisitRecords::class)
            ->callAction('registerVisitor', [
                'organization_id' => $context['organization']->id,
                'visitor_id' => $context['visitor']->id,
                'host_employee_id' => null,
                'partner_id' => null,
                'purpose' => 'ATENDIMENTO NA PORTARIA 6B.11',
                'expected_start_at' => $registeredAt
                    ->copy()
                    ->addHours(3)
                    ->format('Y-m-d H:i:s'),
                'expected_end_at' => null,
            ])
            ->assertHasNoErrors();

        $visit = VisitRecord::query()
            ->where(
                'purpose',
                'ATENDIMENTO NA PORTARIA 6B.11'
            )
            ->sole();

        $this->assertSame(
            VisitStatus::PendingAuthorization,
            $visit->status
        );

        $this->assertTrue(
            $visit->expected_start_at?->equalTo(
                $registeredAt
            ) ?? false
        );

        $this->assertSame(
            $operator->id,
            $visit->arrived_by
        );

        $this->assertTrue(
            $visit->arrived_at?->equalTo(
                $registeredAt
            ) ?? false
        );

        $this->assertSame(
            $operator->id,
            $visit->identity_verified_by
        );

        $this->assertTrue(
            $visit->identity_verified_at?->equalTo(
                $registeredAt
            ) ?? false
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     visitor: VisitorRecord
     * }
     */
    private function context(): array
    {
        app(TenantContext::class)
            ->clearSelectedTenant();

        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO TESTE MODOS DE VISITA',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE TESTE MODOS DE VISITA LTDA',
            'display_name' => 'UNIDADE TESTE MODOS DE VISITA',
            'unit_code' => 'MOD-01',
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE TESTE MODOS DE VISITA',
            'status' => VisitorStatus::Active,
        ]);

        return compact(
            'tenant',
            'organization',
            'visitor'
        );
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(
        array $permissions
    ): User {
        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        $role = Role::findOrCreate(
            'visit_creation_mode_test_'.Str::random(8),
            'web'
        );

        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user;
    }

    private function allowOrganization(
        User $user,
        OrganizationRecord $organization,
        string $role
    ): void {
        $user->organizations()->attach(
            $organization->id,
            [
                'role' => $role,
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        app(TenantContext::class)
            ->initializeForUser($user);
    }
}
