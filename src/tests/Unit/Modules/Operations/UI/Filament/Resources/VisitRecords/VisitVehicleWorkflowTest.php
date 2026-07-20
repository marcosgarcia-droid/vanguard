<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitRecords;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\Support\VehicleCatalog;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Pages\KanbanVisitRecords;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Pages\ListVisitRecords;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Schemas\VisitRecordForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
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

    public function test_create_action_submits_catalog_vehicle_from_kanban(): void
    {
        $context = $this->context(
            createVisit: false
        );

        $manager = $this->userWithPermissions([
            'ViewAny:VisitRecord',
            'Create:VisitRecord',
            'AuthorizeVehicleEntry:VisitRecord',
        ]);

        $this->allowOrganization(
            $manager,
            $context['organization'],
            'manager'
        );

        $this->actingAs($manager);

        $tenantContext = app(TenantContext::class);

        $this->assertTrue(
            auth()->user()?->is($manager) ?? false
        );

        $this->assertSame(
            $context['tenant']->id,
            $tenantContext->selectedTenantId()
        );

        $this->assertSame(
            $context['tenant']->id,
            $tenantContext->currentTenantIdForUser($manager)
        );

        $this->assertTrue(
            $tenantContext->hasOrganizationAccess(
                $manager,
                $context['organization']->id
            )
        );

        $organizationOptions = $this->invokePrivateStatic(
            VisitRecordForm::class,
            'organizationOptions',
            []
        );

        $this->assertArrayHasKey(
            $context['organization']->id,
            $organizationOptions,
            'Opções encontradas: '.json_encode(
                $organizationOptions,
                JSON_UNESCAPED_UNICODE
            )
        );

        Livewire::test(
            KanbanVisitRecords::class
        )
            ->callAction('create', [
                'organization_id' => $context['organization']->id,
                'visitor_id' => $context['visitor']->id,
                'host_employee_id' => null,
                'partner_id' => null,
                'purpose' => 'TESTE LIVEWIRE COM VEÍCULO',
                'expected_start_at' => now()
                    ->addHour()
                    ->format('Y-m-d H:i:s'),
                'expected_end_at' => now()
                    ->addHours(2)
                    ->format('Y-m-d H:i:s'),
                'vehicle_plate' => 'ABC1D23',
                'vehicle_brand' => 'Toyota',
                'vehicle_model' => 'Corolla',
                'vehicle_color' => 'Prata',
                'vehicle_entry_authorized' => false,
            ]);

        $this->assertDatabaseHas('visits', [
            'organization_id' => $context['organization']->id,
            'visitor_id' => $context['visitor']->id,
            'purpose' => 'TESTE LIVEWIRE COM VEÍCULO',
        ]);

        $this->assertDatabaseHas('visit_vehicles', [
            'plate' => 'ABC1D23',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'color' => 'Prata',
        ]);
    }

    public function test_visitado_from_another_unit_in_same_business_group_is_accepted(): void
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

        $otherOrganization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $context['tenant']->id,
            'status' => 'active',
            'legal_name' => 'OUTRA UNIDADE DO MESMO GRUPO LTDA',
            'display_name' => 'OUTRA UNIDADE DO MESMO GRUPO',
            'unit_code' => 'VEI-02',
        ]);

        $employee = $this->createEmployee(
            $otherOrganization,
            'VISITADO DE OUTRA UNIDADE'
        );

        $options = $this->invokePrivateStatic(
            VisitRecordForm::class,
            'employeeOptions',
            [$context['organization']->id]
        );

        $this->assertArrayHasKey(
            $employee->id,
            $options
        );

        $data = $this->validatedCreationData([
            'organization_id' => $context['organization']->id,
            'visitor_id' => $context['visitor']->id,
            'host_employee_id' => $employee->id,
            'partner_id' => null,
            'purpose' => 'VISITA ENTRE UNIDADES',
            'expected_start_at' => now()->addHour(),
            'expected_end_at' => now()->addHours(2),
            'vehicle_entry_authorized' => false,
        ]);

        $this->assertSame(
            $employee->id,
            $data['host_employee_id']
        );
    }

    public function test_visitado_from_another_business_group_is_rejected(): void
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

        $otherTenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'OUTRO GRUPO EMPRESARIAL',
            'status' => 'active',
        ]);

        $otherOrganization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE DE OUTRO GRUPO LTDA',
            'display_name' => 'UNIDADE DE OUTRO GRUPO',
            'unit_code' => 'EXT-01',
        ]);

        $employee = $this->createEmployee(
            $otherOrganization,
            'VISITADO DE OUTRO GRUPO'
        );

        $options = $this->invokePrivateStatic(
            VisitRecordForm::class,
            'employeeOptions',
            [$context['organization']->id]
        );

        $this->assertArrayNotHasKey(
            $employee->id,
            $options
        );

        try {
            $this->validatedCreationData([
                'organization_id' => $context['organization']->id,
                'visitor_id' => $context['visitor']->id,
                'host_employee_id' => $employee->id,
                'partner_id' => null,
                'purpose' => 'VISITA COM VISITADO INVÁLIDO',
                'expected_start_at' => now()->addHour(),
                'expected_end_at' => now()->addHours(2),
                'vehicle_entry_authorized' => false,
            ]);

            $this->fail(
                'Era esperada uma falha de validação para Visitado de outro grupo empresarial.'
            );
        } catch (ValidationException $exception) {
            $this->assertSame(
                [
                    'O visitado selecionado não está disponível para este grupo empresarial.',
                ],
                $exception->errors()['host_employee_id'] ?? []
            );
        }
    }

    public function test_manual_vehicle_options_are_resolved_to_plain_text(): void
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

        $data = $this->validatedCreationData([
            'organization_id' => $context['organization']->id,
            'visitor_id' => $context['visitor']->id,
            'host_employee_id' => null,
            'partner_id' => null,
            'purpose' => 'VISITA COM VEÍCULO FORA DO CATÁLOGO',
            'expected_start_at' => now()->addHour(),
            'expected_end_at' => now()->addHours(2),
            'vehicle_plate' => 'xyz-9a87',
            'vehicle_brand' => VehicleCatalog::OTHER,
            'vehicle_brand_other' => '  Fabricante artesanal  ',
            'vehicle_model' => null,
            'vehicle_model_other' => '  Modelo especial  ',
            'vehicle_color' => VehicleCatalog::OTHER,
            'vehicle_color_other' => '  Azul petróleo  ',
            'vehicle_entry_authorized' => false,
        ]);

        $this->assertSame(
            'Fabricante artesanal',
            $data['vehicle_brand']
        );

        $this->assertSame(
            'Modelo especial',
            $data['vehicle_model']
        );

        $this->assertSame(
            'Azul petróleo',
            $data['vehicle_color']
        );

        $visit = $this->invokePrivateStatic(
            ListVisitRecords::class,
            'createVisitWithVehicle',
            [$data]
        );

        $this->assertDatabaseHas('visit_vehicles', [
            'visit_id' => $visit->id,
            'plate' => 'XYZ9A87',
            'brand' => 'Fabricante artesanal',
            'model' => 'Modelo especial',
            'color' => 'Azul petróleo',
            'entry_authorized' => false,
        ]);
    }

    public function test_backend_rejects_model_from_another_brand(): void
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
                'purpose' => 'MODELO FORJADO',
                'expected_start_at' => now()->addHour(),
                'expected_end_at' => now()->addHours(2),
                'vehicle_plate' => 'ABC1D23',
                'vehicle_brand' => 'Toyota',
                'vehicle_model' => 'Onix',
                'vehicle_color' => 'Prata',
                'vehicle_entry_authorized' => false,
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
            "Select::make('vehicle_brand')",
            "'vehicle_brand_other'",
            "Select::make('vehicle_model')",
            "'vehicle_model_other'",
            "Select::make('vehicle_color')",
            "'vehicle_color_other'",
            'VehicleCatalog::brandOptions()',
            'VehicleCatalog::modelOptions(',
            'VehicleCatalog::colorOptions()',
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
    private function createEmployee(
        OrganizationRecord $organization,
        string $name
    ): EmployeeRecord {
        return EmployeeRecord::query()->create([
            'tenant_id' => $organization->tenant_id,
            'organization_id' => $organization->id,
            'manager_employee_id' => null,
            'employee_code' => 'TEST-'.strtoupper(
                Str::random(10)
            ),
            'full_name' => $name,
            'preferred_name' => null,
            'gender' => null,
            'birth_date' => null,
            'photo_disk' => 'local',
            'photo_path' => null,
            'department' => 'Operações',
            'position' => 'Responsável pelo visitante',
            'employment_type' => 'employee',
            'status' => 'active',
            'hired_at' => now()->subYear()->toDateString(),
            'terminated_at' => null,
            'notes' => 'Registro sintético para teste.',
        ]);
    }

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
