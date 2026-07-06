<?php

namespace Database\Seeders;

use App\Models\User;
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

        foreach (array_merge($organizationPermissions, $employeePermissions, $partnerPermissions) as $permission) {
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
            ->syncPermissions(array_merge($organizationPermissions, $employeePermissions, $partnerPermissions));

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
}
