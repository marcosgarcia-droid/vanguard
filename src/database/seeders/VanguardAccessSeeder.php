<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class VanguardAccessSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        $organizationPermissions = [
            'ViewAny:OrganizationRecord',
            'View:OrganizationRecord',
            'Create:OrganizationRecord',
            'Update:OrganizationRecord',
            'Delete:OrganizationRecord',
            'DeleteAny:OrganizationRecord',
            'Restore:OrganizationRecord',
            'RestoreAny:OrganizationRecord',
            'ForceDelete:OrganizationRecord',
            'ForceDeleteAny:OrganizationRecord',
        ];

        $employeePermissions = [
            'ViewAny:EmployeeRecord',
            'View:EmployeeRecord',
            'Create:EmployeeRecord',
            'Update:EmployeeRecord',
            'Delete:EmployeeRecord',
            'DeleteAny:EmployeeRecord',
            'Restore:EmployeeRecord',
            'RestoreAny:EmployeeRecord',
            'ForceDelete:EmployeeRecord',
            'ForceDeleteAny:EmployeeRecord',
        ];

        $partnerPermissions = [
            'ViewAny:PartnerRecord',
            'View:PartnerRecord',
            'Create:PartnerRecord',
            'Update:PartnerRecord',
            'Delete:PartnerRecord',
            'DeleteAny:PartnerRecord',
            'Restore:PartnerRecord',
            'RestoreAny:PartnerRecord',
            'ForceDelete:PartnerRecord',
            'ForceDeleteAny:PartnerRecord',
        ];

        $classificationPermissions = [
            'ViewAny:ClassificationOptionRecord',
            'View:ClassificationOptionRecord',
            'Create:ClassificationOptionRecord',
            'Update:ClassificationOptionRecord',
            'Delete:ClassificationOptionRecord',
            'DeleteAny:ClassificationOptionRecord',
            'Restore:ClassificationOptionRecord',
            'RestoreAny:ClassificationOptionRecord',
            'ForceDelete:ClassificationOptionRecord',
            'ForceDeleteAny:ClassificationOptionRecord',
        ];

        $workScheduleTemplatePermissions = [
            'ViewAny:EmployeeWorkScheduleTemplateRecord',
            'View:EmployeeWorkScheduleTemplateRecord',
            'Create:EmployeeWorkScheduleTemplateRecord',
            'Update:EmployeeWorkScheduleTemplateRecord',
            'Delete:EmployeeWorkScheduleTemplateRecord',
            'DeleteAny:EmployeeWorkScheduleTemplateRecord',
            'Restore:EmployeeWorkScheduleTemplateRecord',
            'RestoreAny:EmployeeWorkScheduleTemplateRecord',
            'ForceDelete:EmployeeWorkScheduleTemplateRecord',
            'ForceDeleteAny:EmployeeWorkScheduleTemplateRecord',
        ];

        foreach (array_merge(
            $organizationPermissions,
            $employeePermissions,
            $partnerPermissions,
            $classificationPermissions,
            $workScheduleTemplatePermissions,
        ) as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        $roles = [
            'super_admin',
            'panel_user',
            'admin',
            'manager',
            'operator',
            'viewer',
        ];

        foreach ($roles as $role) {
            Role::findOrCreate($role, $guard);
        }

        Role::findByName('super_admin', $guard)
            ->syncPermissions(Permission::query()->pluck('name')->all());

        Role::findByName('panel_user', $guard)
            ->syncPermissions([]);

        Role::findByName('admin', $guard)
            ->syncPermissions(array_merge(
                $organizationPermissions,
                $employeePermissions,
                $partnerPermissions,
                $classificationPermissions,
                $workScheduleTemplatePermissions,
            ));

        Role::findByName('manager', $guard)
            ->syncPermissions([
                'ViewAny:OrganizationRecord',
                'View:OrganizationRecord',
                'Create:OrganizationRecord',
                'Update:OrganizationRecord',
                'ViewAny:EmployeeRecord',
                'View:EmployeeRecord',
                'Create:EmployeeRecord',
                'Update:EmployeeRecord',
                'ViewAny:PartnerRecord',
                'View:PartnerRecord',
                'Create:PartnerRecord',
                'Update:PartnerRecord',
                'ViewAny:ClassificationOptionRecord',
                'View:ClassificationOptionRecord',
                'ViewAny:EmployeeWorkScheduleTemplateRecord',
                'View:EmployeeWorkScheduleTemplateRecord',
            ]);

        Role::findByName('operator', $guard)
            ->syncPermissions([
                'ViewAny:OrganizationRecord',
                'View:OrganizationRecord',
                'Create:OrganizationRecord',
                'Update:OrganizationRecord',
                'ViewAny:EmployeeRecord',
                'View:EmployeeRecord',
                'ViewAny:PartnerRecord',
                'View:PartnerRecord',
                'Create:PartnerRecord',
                'Update:PartnerRecord',
                'ViewAny:ClassificationOptionRecord',
                'View:ClassificationOptionRecord',
                'ViewAny:EmployeeWorkScheduleTemplateRecord',
                'View:EmployeeWorkScheduleTemplateRecord',
            ]);

        Role::findByName('viewer', $guard)
            ->syncPermissions([
                'ViewAny:OrganizationRecord',
                'View:OrganizationRecord',
            ]);

        $tenant = TenantRecord::query()
            ->where('name', 'AGRONORTE')
            ->first();

        if (! $tenant) {
            $tenant = TenantRecord::query()->create([
                'id' => (string) Str::uuid(),
                'name' => 'AGRONORTE',
                'status' => 'active',
            ]);
        }

        $this->seedClassificationOptions($tenant);
        $this->seedWorkScheduleTemplates($tenant);

        $users = [
            [
                'name' => 'Administrador Teste',
                'email' => 'admin@vanguard.test',
                'roles' => ['panel_user', 'admin'],
                'tenant_role' => 'admin',
                'is_owner' => true,
            ],
            [
                'name' => 'Gestor Teste',
                'email' => 'gestor@vanguard.test',
                'roles' => ['panel_user', 'manager'],
                'tenant_role' => 'manager',
                'is_owner' => false,
            ],
            [
                'name' => 'Operador Teste',
                'email' => 'operador@vanguard.test',
                'roles' => ['panel_user', 'operator'],
                'tenant_role' => 'operator',
                'is_owner' => false,
            ],
            [
                'name' => 'Visualizador Teste',
                'email' => 'visualizador@vanguard.test',
                'roles' => ['panel_user', 'viewer'],
                'tenant_role' => 'viewer',
                'is_owner' => false,
            ],
        ];

        foreach ($users as $userData) {
            $user = User::query()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ],
            );

            $user->syncRoles($userData['roles']);

            $tenant->users()->syncWithoutDetaching([
                $user->id => [
                    'role' => $userData['tenant_role'],
                    'is_owner' => $userData['is_owner'],
                    'is_active' => true,
                    'joined_at' => now(),
                ],
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedClassificationOptions(TenantRecord $tenant): void
    {
        $defaults = [
            'partner_profile' => [
                'customer' => 'Cliente',
                'supplier' => 'Fornecedor',
                'carrier' => 'Transportadora',
                'service_provider' => 'Prestador de serviço',
                'rural_producer' => 'Produtor rural',
                'other' => 'Outro',
            ],
            'partner_document_type' => [
                'cnpj' => 'CNPJ',
                'cpf' => 'CPF',
                'state_registration' => 'Inscrição Estadual',
                'municipal_registration' => 'Inscrição Municipal',
                'rg' => 'RG',
                'other' => 'Outro',
            ],
            'partner_contact_type' => [
                'mobile' => 'Celular',
                'whatsapp' => 'WhatsApp',
                'phone' => 'Telefone',
                'email' => 'E-mail',
                'contact_person' => 'Pessoa de contato',
                'other' => 'Outro',
            ],
            'partner_address_type' => [
                'fiscal' => 'Fiscal',
                'operational' => 'Operacional',
                'billing' => 'Cobrança',
                'delivery' => 'Entrega',
                'other' => 'Outro',
            ],
        ];

        foreach ($defaults as $category => $items) {
            $sortOrder = 10;

            foreach ($items as $code => $name) {
                ClassificationOptionRecord::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'category' => $category,
                        'code' => $code,
                    ],
                    [
                        'name' => $name,
                        'status' => 'active',
                        'sort_order' => $sortOrder,
                        'is_system' => true,
                    ],
                );

                $sortOrder += 10;
            }
        }
    }

    private function seedWorkScheduleTemplates(TenantRecord $tenant): void
    {
        $templates = [
            [
                'code' => 'administrativo_44h',
                'name' => 'Administrativo 44h',
                'type' => 'standard',
                'description' => '08:00 às 12:00 - 13:00 às 17:48 - SAB DOM DSR',
                'weekly_workload_minutes' => 2640,
                'daily_workload_minutes' => 528,
                'tolerance_before_start_minutes' => 30,
                'tolerance_after_end_minutes' => 0,
                'days' => $this->standardWeekDays('08:00', '17:48', '12:00', '13:00'),
            ],
            [
                'code' => 'comercial_40h',
                'name' => 'Comercial 40h',
                'type' => 'standard',
                'description' => '08:00 às 12:00 - 13:00 às 17:00 - SAB DOM DSR',
                'weekly_workload_minutes' => 2400,
                'daily_workload_minutes' => 480,
                'tolerance_before_start_minutes' => 30,
                'tolerance_after_end_minutes' => 0,
                'days' => $this->standardWeekDays('08:00', '17:00', '12:00', '13:00'),
            ],
            [
                'code' => 'escala_12x36',
                'name' => 'Escala 12x36',
                'type' => 'shift_12x36',
                'description' => '07:00 às 19:00 - Escala 12x36',
                'weekly_workload_minutes' => null,
                'daily_workload_minutes' => 720,
                'tolerance_before_start_minutes' => 30,
                'tolerance_after_end_minutes' => 0,
                'days' => [],
            ],
        ];

        foreach ($templates as $templateData) {
            $days = $templateData['days'];
            unset($templateData['days']);

            $template = EmployeeWorkScheduleTemplateRecord::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'code' => $templateData['code'],
                ],
                array_merge($templateData, [
                    'status' => 'active',
                    'is_system' => true,
                ]),
            );

            foreach ($days as $day) {
                $template->days()->updateOrCreate(
                    [
                        'weekday' => $day['weekday'],
                        'sequence' => $day['sequence'],
                    ],
                    $day,
                );
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function standardWeekDays(string $start, string $end, string $breakStart, string $breakEnd): array
    {
        $days = [];

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            $days[] = [
                'weekday' => $weekday,
                'sequence' => 1,
                'is_working_day' => true,
                'work_starts_at' => $start,
                'work_ends_at' => $end,
                'break_starts_at' => $breakStart,
                'break_ends_at' => $breakEnd,
                'ends_next_day' => false,
                'notes' => null,
            ];
        }

        foreach ([6, 7] as $weekday) {
            $days[] = [
                'weekday' => $weekday,
                'sequence' => 1,
                'is_working_day' => false,
                'work_starts_at' => null,
                'work_ends_at' => null,
                'break_starts_at' => null,
                'break_ends_at' => null,
                'ends_next_day' => false,
                'notes' => 'DSR',
            ];
        }

        return $days;
    }
}
