<?php

namespace Tests\Unit\Modules\Operations\UI\Notifications;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Notifications\VisitGatehouseDecisionNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisitGatehouseDecisionNotifierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate(
            'OperateGatehouse:VisitRecord',
            'web'
        );

        Role::findOrCreate(
            config(
                'filament-shield.super_admin.name',
                'super_admin'
            ),
            'web'
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    public function test_authorization_notifies_only_gatehouse_users_allowed_for_the_unit(): void
    {
        $context = $this->context();

        $otherOrganization = $this->organization(
            $context['tenant'],
            'OUTRA UNIDADE',
            'OUT-01'
        );

        $gatehouseRole = $this->gatehouseRole();

        $decisionMaker = User::factory()->create([
            'name' => 'FUNCIONÁRIO VISITADO',
        ]);

        $allowedOperator = User::factory()->create([
            'name' => 'OPERADOR DA UNIDADE',
        ]);

        $crossUnitOperator = User::factory()->create([
            'name' => 'OPERADOR DE OUTRA UNIDADE',
        ]);

        $sameUnitWithoutPermission = User::factory()->create([
            'name' => 'USUÁRIO SEM PERMISSÃO',
        ]);

        $superAdmin = User::factory()->create([
            'name' => 'SUPER ADMINISTRADOR',
        ]);

        foreach ([
            $decisionMaker,
            $allowedOperator,
            $crossUnitOperator,
        ] as $user) {
            $user->assignRole($gatehouseRole);
        }

        $superAdmin->assignRole(
            Role::findOrCreate(
                config(
                    'filament-shield.super_admin.name',
                    'super_admin'
                ),
                'web'
            )
        );

        $decisionMaker->organizations()->attach(
            $context['organization']->id,
            $this->activeOrganizationPivot('manager')
        );

        $allowedOperator->organizations()->attach(
            $context['organization']->id,
            $this->activeOrganizationPivot('operator')
        );

        $sameUnitWithoutPermission->organizations()->attach(
            $context['organization']->id,
            $this->activeOrganizationPivot('viewer')
        );

        $crossUnitOperator->organizations()->attach(
            $otherOrganization->id,
            $this->activeOrganizationPivot('operator')
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $context['host']->forceFill([
            'user_id' => $decisionMaker->id,
        ])->save();

        $context['visit']->forceFill([
            'status' => VisitStatus::Authorized,
            'authorizer_employee_id' => $context['host']->id,
            'authorized_by' => $decisionMaker->id,
            'authorized_at' => now(),
        ])->save();

        app(VisitGatehouseDecisionNotifier::class)
            ->notifyAuthorizedByHost(
                $context['visit'],
                $decisionMaker->id
            );

        $this->assertDatabaseCount(
            'notifications',
            2
        );

        $this->assertNotificationContains(
            $allowedOperator,
            [
                'Visita autorizada pelo visitado',
                'FUNCIONÁRIO VISITADO',
                'VISITANTE RETORNO À PORTARIA',
                'UNIDADE RETORNO À PORTARIA',
                'Visualizar visita',
            ]
        );

        $this->assertNotificationContains(
            $superAdmin,
            [
                'Visita autorizada pelo visitado',
                'VISITANTE RETORNO À PORTARIA',
            ]
        );

        $this->assertNotificationDoesNotExistFor(
            $decisionMaker
        );

        $this->assertNotificationDoesNotExistFor(
            $crossUnitOperator
        );

        $this->assertNotificationDoesNotExistFor(
            $sameUnitWithoutPermission
        );

        $data = $this->notificationDataFor(
            $allowedOperator
        );

        $action = collect(
            $data['actions'] ?? []
        )->firstWhere(
            'name',
            'openVisit'
        );

        $this->assertIsArray($action);

        $url = urldecode(
            (string) ($action['url'] ?? '')
        );

        $this->assertStringContainsString(
            'tableAction=view',
            $url
        );

        $this->assertStringContainsString(
            'tableActionRecord='.$context['visit']->id,
            $url
        );

        $this->assertTrue(
            (bool) (
                $action['shouldMarkAsRead']
                ?? false
            )
        );

        $this->assertFalse(
            (bool) (
                $action['shouldPostToUrl']
                ?? true
            )
        );
    }

    public function test_rejection_notification_includes_the_reason(): void
    {
        $context = $this->context();

        $operator = User::factory()->create([
            'name' => 'OPERADOR DA PORTARIA',
        ]);

        $operator->assignRole(
            $this->gatehouseRole()
        );

        $operator->organizations()->attach(
            $context['organization']->id,
            $this->activeOrganizationPivot('operator')
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $decisionMaker = User::factory()->create([
            'name' => 'FUNCIONÁRIO VISITADO',
        ]);

        $context['host']->forceFill([
            'user_id' => $decisionMaker->id,
        ])->save();

        $context['visit']->forceFill([
            'status' => VisitStatus::Rejected,
            'rejected_by' => $decisionMaker->id,
            'rejected_at' => now(),
            'rejection_reason' => 'Não poderei receber o visitante neste momento.',
        ])->save();

        app(VisitGatehouseDecisionNotifier::class)
            ->notifyRejectedByHost(
                $context['visit'],
                $decisionMaker->id
            );

        $this->assertDatabaseCount(
            'notifications',
            1
        );

        $this->assertNotificationContains(
            $operator,
            [
                'Visita não autorizada pelo visitado',
                'FUNCIONÁRIO VISITADO',
                'VISITANTE RETORNO À PORTARIA',
                'Não poderei receber o visitante neste momento.',
                'Visualizar visita',
            ]
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     visitor: VisitorRecord,
     *     host: EmployeeRecord,
     *     visit: VisitRecord
     * }
     */
    private function context(): array
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO RETORNO À PORTARIA',
            'status' => 'active',
        ]);

        $organization = $this->organization(
            $tenant,
            'UNIDADE RETORNO À PORTARIA',
            'RET-01'
        );

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE RETORNO À PORTARIA',
            'status' => VisitorStatus::Active,
        ]);

        $host = EmployeeRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'FUNCIONÁRIO VISITADO',
            'employment_type' => 'employee',
            'status' => 'active',
        ]);

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'host_employee_id' => $host->id,
            'status' => VisitStatus::PendingAuthorization,
            'purpose' => 'VALIDAÇÃO DO RETORNO À PORTARIA',
            'expected_start_at' => now(),
            'arrived_at' => now(),
        ]);

        return compact(
            'tenant',
            'organization',
            'visitor',
            'host',
            'visit'
        );
    }

    private function organization(
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

    private function gatehouseRole(): Role
    {
        $role = Role::findOrCreate(
            'visit_gatehouse_decision_notifier_test',
            'web'
        );

        $role->syncPermissions([
            'OperateGatehouse:VisitRecord',
        ]);

        return $role;
    }

    /**
     * @return array{
     *     role: string,
     *     is_active: bool,
     *     granted_at: mixed
     * }
     */
    private function activeOrganizationPivot(
        string $role
    ): array {
        return [
            'role' => $role,
            'is_active' => true,
            'granted_at' => now(),
        ];
    }

    /**
     * @param  list<string>  $expectedFragments
     */
    private function assertNotificationContains(
        User $user,
        array $expectedFragments
    ): void {
        $data = $this->notificationDataFor(
            $user
        );

        $serialized = json_encode(
            $data,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
        );

        foreach ($expectedFragments as $fragment) {
            $this->assertStringContainsString(
                $fragment,
                $serialized
            );
        }
    }

    private function assertNotificationDoesNotExistFor(
        User $user
    ): void {
        $this->assertFalse(
            DB::table('notifications')
                ->where(
                    'notifiable_type',
                    User::class
                )
                ->where(
                    'notifiable_id',
                    $user->id
                )
                ->exists()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationDataFor(
        User $user
    ): array {
        $notification = DB::table('notifications')
            ->where(
                'notifiable_type',
                User::class
            )
            ->where(
                'notifiable_id',
                $user->id
            )
            ->latest('created_at')
            ->first();

        $this->assertNotNull($notification);

        return json_decode(
            (string) $notification->data,
            true,
            flags: JSON_THROW_ON_ERROR
        );
    }
}
