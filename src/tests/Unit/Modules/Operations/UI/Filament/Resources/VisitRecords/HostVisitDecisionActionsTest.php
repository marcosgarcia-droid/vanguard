<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitRecords;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitAuthorizationMethod;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Pages\ListVisitRecords;
use App\Modules\Operations\UI\Notifications\VisitHostNotifier;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HostVisitDecisionActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_linked_host_sees_the_host_decision_actions(): void
    {
        $context = $this->context();

        $hostUser = $this->userWithPermissions([
            'ViewAny:VisitRecord',
            'View:VisitRecord',
        ]);

        $otherManager = $this->userWithPermissions([
            'ViewAny:VisitRecord',
            'View:VisitRecord',
            'Update:VisitRecord',
        ]);

        $this->allowOrganization(
            $hostUser,
            $context['organization'],
            'manager'
        );

        $this->allowOrganization(
            $otherManager,
            $context['organization'],
            'manager'
        );

        $context['host']->forceFill([
            'user_id' => $hostUser->id,
        ])->save();

        $this->actingAs($hostUser);

        Livewire::test(ListVisitRecords::class)
            ->assertActionVisible(
                TestAction::make('authorizeHostVisit')
                    ->table($context['visit'])
            )
            ->assertActionVisible(
                TestAction::make('rejectHostVisit')
                    ->table($context['visit'])
            )
            ->assertActionHidden(
                TestAction::make('authorizeVisit')
                    ->table($context['visit'])
            )
            ->assertActionHidden(
                TestAction::make('rejectVisit')
                    ->table($context['visit'])
            );

        app(TenantContext::class)
            ->clearSelectedTenant();

        $this->actingAs($otherManager);

        app(TenantContext::class)
            ->initializeForUser($otherManager);

        Livewire::test(ListVisitRecords::class)
            ->assertActionHidden(
                TestAction::make('authorizeHostVisit')
                    ->table($context['visit'])
            )
            ->assertActionHidden(
                TestAction::make('rejectHostVisit')
                    ->table($context['visit'])
            );
    }

    public function test_the_linked_host_can_authorize_the_visit(): void
    {
        $context = $this->context();

        $hostUser = $this->userWithPermissions([
            'ViewAny:VisitRecord',
            'View:VisitRecord',
        ]);

        $this->allowOrganization(
            $hostUser,
            $context['organization'],
            'manager'
        );

        $context['host']->forceFill([
            'user_id' => $hostUser->id,
        ])->save();

        app(VisitHostNotifier::class)
            ->notifyArrival(
                $context['visit']->fresh([
                    'visitor',
                    'organization',
                    'hostEmployee.user',
                ])
            );

        $this->actingAs($hostUser);

        Livewire::test(ListVisitRecords::class)
            ->callAction(
                TestAction::make('authorizeHostVisit')
                    ->table($context['visit']),
                [
                    'authorization_notes' => '  Autorizado diretamente pelo visitado.  ',
                ]
            )
            ->assertHasNoErrors();

        $visit = $context['visit']->fresh();

        $this->assertSame(
            VisitStatus::Authorized,
            $visit->status
        );

        $this->assertSame(
            $context['host']->id,
            $visit->authorizer_employee_id
        );

        $this->assertSame(
            $hostUser->id,
            $visit->authorized_by
        );

        $this->assertSame(
            VisitAuthorizationMethod::System,
            $visit->authorization_method
        );

        $this->assertSame(
            'Autorizado diretamente pelo visitado.',
            $visit->authorization_notes
        );

        $this->assertNotNull($visit->authorized_at);
        $this->assertNull($visit->rejected_at);

        $this->assertHostDecisionNotificationClosed(
            $hostUser,
            $visit
        );
    }

    public function test_the_linked_host_can_reject_the_visit(): void
    {
        $context = $this->context();

        $hostUser = $this->userWithPermissions([
            'ViewAny:VisitRecord',
            'View:VisitRecord',
        ]);

        $this->allowOrganization(
            $hostUser,
            $context['organization'],
            'manager'
        );

        $context['host']->forceFill([
            'user_id' => $hostUser->id,
        ])->save();

        app(VisitHostNotifier::class)
            ->notifyArrival(
                $context['visit']->fresh([
                    'visitor',
                    'organization',
                    'hostEmployee.user',
                ])
            );

        $this->actingAs($hostUser);

        Livewire::test(ListVisitRecords::class)
            ->callAction(
                TestAction::make('rejectHostVisit')
                    ->table($context['visit']),
                [
                    'rejection_reason' => '  Não poderei receber o visitante neste momento.  ',
                ]
            )
            ->assertHasNoErrors();

        $visit = $context['visit']->fresh();

        $this->assertSame(
            VisitStatus::Rejected,
            $visit->status
        );

        $this->assertSame(
            $hostUser->id,
            $visit->rejected_by
        );

        $this->assertSame(
            'Não poderei receber o visitante neste momento.',
            $visit->rejection_reason
        );

        $this->assertNotNull($visit->rejected_at);
        $this->assertNull($visit->authorized_at);
        $this->assertNull($visit->authorized_by);

        $this->assertHostDecisionNotificationClosed(
            $hostUser,
            $visit
        );
    }

    public function test_gatehouse_authorization_closes_the_host_decision_notification(): void
    {
        $context = $this->context();

        $hostUser = $this->userWithPermissions([
            'ViewAny:VisitRecord',
            'View:VisitRecord',
        ]);

        $gatehouseUser = $this->userWithPermissions([
            'ViewAny:VisitRecord',
            'View:VisitRecord',
            'OperateGatehouse:VisitRecord',
        ]);

        $this->allowOrganization(
            $hostUser,
            $context['organization'],
            'manager'
        );

        $this->allowOrganization(
            $gatehouseUser,
            $context['organization'],
            'gatehouse'
        );

        $context['host']->forceFill([
            'user_id' => $hostUser->id,
        ])->save();

        app(VisitHostNotifier::class)
            ->notifyArrival(
                $context['visit']->fresh([
                    'visitor',
                    'organization',
                    'hostEmployee.user',
                ])
            );

        $this->actingAs($gatehouseUser);

        Livewire::test(ListVisitRecords::class)
            ->callAction(
                TestAction::make('authorizeVisit')
                    ->table($context['visit']),
                [
                    'authorizer_employee_id' => $context['host']->id,
                    'authorization_method' => VisitAuthorizationMethod::Phone->value,
                    'authorization_notes' => 'Autorizado pela portaria após contato telefônico.',
                ]
            )
            ->assertHasNoErrors();

        $visit = $context['visit']->fresh();

        $this->assertSame(
            VisitStatus::Authorized,
            $visit->status
        );

        $this->assertSame(
            $gatehouseUser->id,
            $visit->authorized_by
        );

        $this->assertSame(
            VisitAuthorizationMethod::Phone,
            $visit->authorization_method
        );

        $this->assertSame(
            'Autorizado pela portaria após contato telefônico.',
            $visit->authorization_notes
        );

        $this->assertHostDecisionNotificationClosed(
            $hostUser,
            $visit
        );

        $this->assertDatabaseCount(
            'notifications',
            1
        );
    }

    public function test_gatehouse_rejection_closes_the_host_decision_notification(): void
    {
        $context = $this->context();

        $hostUser = $this->userWithPermissions([
            'ViewAny:VisitRecord',
            'View:VisitRecord',
        ]);

        $gatehouseUser = $this->userWithPermissions([
            'ViewAny:VisitRecord',
            'View:VisitRecord',
            'OperateGatehouse:VisitRecord',
        ]);

        $this->allowOrganization(
            $hostUser,
            $context['organization'],
            'manager'
        );

        $this->allowOrganization(
            $gatehouseUser,
            $context['organization'],
            'gatehouse'
        );

        $context['host']->forceFill([
            'user_id' => $hostUser->id,
        ])->save();

        app(VisitHostNotifier::class)
            ->notifyArrival(
                $context['visit']->fresh([
                    'visitor',
                    'organization',
                    'hostEmployee.user',
                ])
            );

        $this->actingAs($gatehouseUser);

        Livewire::test(ListVisitRecords::class)
            ->callAction(
                TestAction::make('rejectVisit')
                    ->table($context['visit']),
                [
                    'rejection_reason' => 'Não autorizado após contato realizado pela portaria.',
                ]
            )
            ->assertHasNoErrors();

        $visit = $context['visit']->fresh();

        $this->assertSame(
            VisitStatus::Rejected,
            $visit->status
        );

        $this->assertSame(
            $gatehouseUser->id,
            $visit->rejected_by
        );

        $this->assertSame(
            'Não autorizado após contato realizado pela portaria.',
            $visit->rejection_reason
        );

        $this->assertHostDecisionNotificationClosed(
            $hostUser,
            $visit
        );

        $this->assertDatabaseCount(
            'notifications',
            1
        );
    }

    private function assertHostDecisionNotificationClosed(
        User $hostUser,
        VisitRecord $visit
    ): void {
        $notification = DB::table(
            'notifications'
        )
            ->where(
                'notifiable_type',
                User::class
            )
            ->where(
                'notifiable_id',
                $hostUser->id
            )
            ->sole();

        $data = json_decode(
            (string) $notification->data,
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $actions = collect(
            $data['actions'] ?? []
        );

        $this->assertSame(
            ['openVisit'],
            $actions
                ->pluck('name')
                ->all()
        );

        $this->assertSame(
            $visit->id,
            $data['viewData']['visit_id']
                ?? null
        );

        $this->assertSame(
            $visit->status->value,
            $data['viewData'][
                'decision_status'
            ] ?? null
        );

        $action = $actions->first();

        $this->assertIsArray($action);

        $this->assertSame(
            'Visualizar visita',
            $action['label'] ?? null
        );

        $url = urldecode(
            (string) (
                $action['url']
                ?? ''
            )
        );

        $this->assertStringContainsString(
            'tableAction=view',
            $url
        );

        $this->assertStringContainsString(
            'tableActionRecord='.$visit->id,
            $url
        );

        $this->assertFalse(
            $actions->contains(
                fn (array $candidate): bool => in_array(
                    $candidate['name'] ?? null,
                    [
                        'authorizeHostVisit',
                        'rejectHostVisit',
                    ],
                    true
                )
            )
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
        app(TenantContext::class)
            ->clearSelectedTenant();

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

        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO DECISÃO DO VISITADO',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE DECISÃO DO VISITADO LTDA',
            'display_name' => 'UNIDADE DECISÃO DO VISITADO',
            'unit_code' => 'HST-01',
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE DECISÃO DO VISITADO',
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
            'purpose' => 'VALIDAÇÃO DAS ACTIONS DO VISITADO',
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
            'host_visit_decision_test_'.Str::random(8),
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
