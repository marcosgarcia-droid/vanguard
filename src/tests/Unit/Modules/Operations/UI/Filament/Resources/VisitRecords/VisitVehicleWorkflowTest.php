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
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Pages\ListVisitRecords;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Schemas\VisitRecordForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisitVehicleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_is_normalized_and_linked_to_visit(): void
    {
        $context = $this->context();

        $user = User::factory()->create();

        $vehicle = $context['visit']->vehicle()->create([
            'plate' => 'abc-1d23',
            'brand' => '  Toyota  ',
            'model' => '  Corolla  ',
            'color' => '  Prata  ',
            'entry_authorized' => true,
            'entry_authorized_by' => $user->id,
            'entry_authorized_at' => now(),
        ]);

        $this->assertSame(
            'ABC1D23',
            $vehicle->fresh()->plate
        );

        $this->assertSame(
            'Toyota',
            $vehicle->fresh()->brand
        );

        $this->assertTrue(
            $vehicle->fresh()->entry_authorized
        );

        $this->assertTrue(
            $context['visit']->fresh()->vehicle->is($vehicle)
        );

        $vehicle->update([
            'entry_authorized' => false,
        ]);

        $vehicle->refresh();

        $this->assertFalse($vehicle->entry_authorized);
        $this->assertNull($vehicle->entry_authorized_by);
        $this->assertNull($vehicle->entry_authorized_at);
    }

    public function test_only_manager_permission_or_super_admin_authorizes_vehicle_entry(): void
    {
        $context = $this->context();

        $manager = $this->userWithPermissions([
            'AuthorizeVehicleEntry:VisitRecord',
        ]);

        $operator = $this->userWithPermissions([]);

        $superAdminRole = Role::findOrCreate(
            config(
                'filament-shield.super_admin.name',
                'super_admin'
            ),
            'web'
        );

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole($superAdminRole);

        $this->allowOrganization(
            $manager,
            $context['organization'],
            'manager'
        );

        $this->allowOrganization(
            $operator,
            $context['organization'],
            'operator'
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->assertTrue(
            $manager->can(
                'authorizeVehicleEntry',
                $context['visit']
            )
        );

        $this->assertFalse(
            $operator->can(
                'authorizeVehicleEntry',
                $context['visit']
            )
        );

        $this->assertTrue(
            $superAdmin->can(
                'authorizeVehicleEntry',
                $context['visit']
            )
        );
    }

    public function test_manager_creates_visit_and_authorized_vehicle_transactionally(): void
    {
        $context = $this->context(
            createVisit: false
        );

        $manager = $this->userWithPermissions([
            'AuthorizeVehicleEntry:VisitRecord',
        ]);

        $this->allowOrganization(
            $manager,
            $context['organization'],
            'manager'
        );

        $this->actingAs($manager);

        $data = $this->validatedCreationData([
            'organization_id' => $context['organization']->id,
            'visitor_id' => $context['visitor']->id,
            'host_employee_id' => null,
            'partner_id' => null,
            'purpose' => 'VISITA COM VEÍCULO',
            'expected_start_at' => now()->addHour(),
            'expected_end_at' => now()->addHours(2),
            'vehicle_plate' => 'abc-1d23',
            'vehicle_brand' => 'Toyota',
            'vehicle_model' => 'Corolla',
            'vehicle_color' => 'Prata',
            'vehicle_entry_authorized' => true,
        ]);

        $visit = $this->invokePrivateStatic(
            ListVisitRecords::class,
            'createVisitWithVehicle',
            [$data]
        );

        $this->assertInstanceOf(
            VisitRecord::class,
            $visit
        );

        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'tenant_id' => $context['tenant']->id,
            'organization_id' => $context['organization']->id,
            'visitor_id' => $context['visitor']->id,
            'status' => VisitStatus::Scheduled->value,
        ]);

        $this->assertDatabaseHas('visit_vehicles', [
            'visit_id' => $visit->id,
            'plate' => 'ABC1D23',
            'entry_authorized' => true,
            'entry_authorized_by' => $manager->id,
        ]);

        $this->assertNotNull(
            $visit->fresh()->vehicle->entry_authorized_at
        );
    }

    public function test_operator_cannot_force_vehicle_authorization_in_backend(): void
    {
        $context = $this->context(
            createVisit: false
        );

        $operator = $this->userWithPermissions([]);

        $this->allowOrganization(
            $operator,
            $context['organization'],
            'operator'
        );

        $this->actingAs($operator);

        $this->expectException(
            ValidationException::class
        );

        try {
            $this->validatedCreationData([
                'organization_id' => $context['organization']->id,
                'visitor_id' => $context['visitor']->id,
                'host_employee_id' => null,
                'partner_id' => null,
                'purpose' => 'TENTATIVA NÃO AUTORIZADA',
                'expected_start_at' => now()->addHour(),
                'expected_end_at' => now()->addHours(2),
                'vehicle_plate' => 'ABC1D23',
                'vehicle_brand' => 'Toyota',
                'vehicle_model' => 'Corolla',
                'vehicle_color' => 'Prata',
                'vehicle_entry_authorized' => true,
            ]);
        } finally {
            $this->assertDatabaseCount(
                'visit_vehicles',
                0
            );
        }
    }

    public function test_form_declares_vehicle_tab_and_authorization_permission(): void
    {
        $filename = (
            new ReflectionClass(VisitRecordForm::class)
        )->getFileName();

        $this->assertIsString($filename);

        $source = file_get_contents($filename);

        $this->assertIsString($source);

        foreach ([
            "Tab::make('Veículo')",
            "TextInput::make('vehicle_plate')",
            "TextInput::make('vehicle_brand')",
            "TextInput::make('vehicle_model')",
            "TextInput::make('vehicle_color')",
            'Toggle::make(',
            "'vehicle_entry_authorized'",
            'AuthorizeVehicleEntry:VisitRecord',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validatedCreationData(
        array $data
    ): array {
        return $this->invokePrivateStatic(
            ListVisitRecords::class,
            'validatedCreationData',
            [$data]
        );
    }

    /**
     * @param  class-string  $class
     * @param  list<mixed>  $arguments
     */
    private function invokePrivateStatic(
        string $class,
        string $method,
        array $arguments
    ): mixed {
        $reflection = new ReflectionMethod(
            $class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            null,
            $arguments
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     visitor: VisitorRecord,
     *     visit: VisitRecord|null
     * }
     */
    private function context(
        bool $createVisit = true
    ): array {
        app(TenantContext::class)
            ->clearSelectedTenant();

        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO TESTE VEÍCULO',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE TESTE VEÍCULO LTDA',
            'display_name' => 'UNIDADE TESTE VEÍCULO',
            'unit_code' => 'VEI-01',
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE COM VEÍCULO',
            'status' => VisitorStatus::Active,
        ]);

        $visit = $createVisit
            ? VisitRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'visitor_id' => $visitor->id,
                'status' => VisitStatus::Scheduled,
                'purpose' => 'VISITA TESTE',
                'expected_start_at' => now()->addHour(),
                'expected_end_at' => now()->addHours(2),
            ])
            : null;

        return compact(
            'tenant',
            'organization',
            'visitor',
            'visit'
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
            'visit_vehicle_test_'.Str::random(8),
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
