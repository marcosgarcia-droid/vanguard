<?php

namespace Tests\Unit\Modules\Operations\UI\Notifications;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Domain\Visits\VisitVehicleAuthorizationStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleAuthorizationRequestRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleRecord;
use App\Modules\Operations\UI\Notifications\VisitVehicleAuthorizationNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisitVehicleAuthorizationNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_notifies_only_users_allowed_to_decide_the_request(): void
    {
        [
            $tenant,
            $organization,
            $visit,
            $vehicle,
        ] = $this->createVisitContext();

        $otherOrganization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'OUTRA UNIDADE LTDA',
            'display_name' => 'OUTRA UNIDADE',
        ]);

        $authorizerRole = $this->authorizerRole();

        $requester = User::factory()->create([
            'name' => 'OPERADOR SOLICITANTE',
        ]);

        $allowedManager = User::factory()->create([
            'name' => 'GESTOR DA UNIDADE',
        ]);

        $crossUnitManager = User::factory()->create([
            'name' => 'GESTOR DE OUTRA UNIDADE',
        ]);

        $superAdmin = User::factory()->create([
            'name' => 'SUPER ADMINISTRADOR',
        ]);

        $requester->organizations()->attach(
            $organization->id,
            $this->activeOrganizationPivot('operator')
        );

        $allowedManager->assignRole($authorizerRole);
        $allowedManager->organizations()->attach(
            $organization->id,
            $this->activeOrganizationPivot('manager')
        );

        $crossUnitManager->assignRole($authorizerRole);
        $crossUnitManager->organizations()->attach(
            $otherOrganization->id,
            $this->activeOrganizationPivot('manager')
        );

        $superAdmin->assignRole(
            Role::findOrCreate(
                config(
                    'filament-shield.super_admin.name',
                    'super_admin'
                ),
                'web'
            )
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $request = VisitVehicleAuthorizationRequestRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'visit_id' => $visit->id,
                'visit_vehicle_id' => $vehicle->id,
                'status' => VisitVehicleAuthorizationStatus::Pending,
                'pending_marker' => true,
                'idempotency_key' => 'notify-request-'.$visit->id,
                'requested_by_user_id' => $requester->id,
                'requested_by_name' => $requester->name,
                'requested_at' => now(),
            ]);

        app(VisitVehicleAuthorizationNotifier::class)
            ->notifyRequestCreated($request);

        $this->assertDatabaseCount('notifications', 2);

        $this->assertNotificationExistsFor(
            $allowedManager,
            [
                'Autorização de veículo pendente',
                'ABC1D23',
                'VISITANTE COM VEÍCULO',
                'UNIDADE VEÍCULO',
                'Abrir visitas',
            ]
        );

        $this->assertNotificationExistsFor(
            $superAdmin,
            [
                'Autorização de veículo pendente',
                'OPERADOR SOLICITANTE',
            ]
        );

        $this->assertNotificationDoesNotExistFor($requester);
        $this->assertNotificationDoesNotExistFor(
            $crossUnitManager
        );
    }

    public function test_it_notifies_the_requester_after_authorization(): void
    {
        [
            $tenant,
            $organization,
            $visit,
            $vehicle,
        ] = $this->createVisitContext();

        $requester = User::factory()->create([
            'name' => 'OPERADOR SOLICITANTE',
        ]);

        $manager = User::factory()->create([
            'name' => 'GESTOR AUTORIZADOR',
        ]);

        $requester->organizations()->attach(
            $organization->id,
            $this->activeOrganizationPivot('operator')
        );

        $request = VisitVehicleAuthorizationRequestRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'visit_id' => $visit->id,
                'visit_vehicle_id' => $vehicle->id,
                'status' => VisitVehicleAuthorizationStatus::Authorized,
                'pending_marker' => null,
                'idempotency_key' => 'notify-authorized-'.$visit->id,
                'requested_by_user_id' => $requester->id,
                'requested_by_name' => $requester->name,
                'requested_at' => now()->subMinute(),
                'decided_by_user_id' => $manager->id,
                'decided_by_name' => $manager->name,
                'decision_notes' => 'Entrada liberada.',
                'decided_at' => now(),
            ]);

        app(VisitVehicleAuthorizationNotifier::class)
            ->notifyDecision($request);

        $this->assertDatabaseCount('notifications', 1);

        $this->assertNotificationExistsFor(
            $requester,
            [
                'Entrada do veículo autorizada',
                'ABC1D23',
                'VISITANTE COM VEÍCULO',
                'GESTOR AUTORIZADOR',
            ]
        );

        $this->assertNotificationDoesNotExistFor($manager);
    }

    public function test_it_includes_the_rejection_reason_in_the_notification(): void
    {
        [
            $tenant,
            $organization,
            $visit,
            $vehicle,
        ] = $this->createVisitContext();

        $requester = User::factory()->create([
            'name' => 'OPERADOR SOLICITANTE',
        ]);

        $manager = User::factory()->create([
            'name' => 'GESTOR RESPONSÁVEL',
        ]);

        $request = VisitVehicleAuthorizationRequestRecord::query()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'visit_id' => $visit->id,
                'visit_vehicle_id' => $vehicle->id,
                'status' => VisitVehicleAuthorizationStatus::Rejected,
                'pending_marker' => null,
                'idempotency_key' => 'notify-rejected-'.$visit->id,
                'requested_by_user_id' => $requester->id,
                'requested_by_name' => $requester->name,
                'requested_at' => now()->subMinute(),
                'decided_by_user_id' => $manager->id,
                'decided_by_name' => $manager->name,
                'decision_notes' => 'Veículo não autorizado para acessar o pátio.',
                'decided_at' => now(),
            ]);

        app(VisitVehicleAuthorizationNotifier::class)
            ->notifyDecision($request);

        $this->assertNotificationExistsFor(
            $requester,
            [
                'Entrada do veículo recusada',
                'Veículo não autorizado para acessar o pátio.',
                'GESTOR RESPONSÁVEL',
            ]
        );
    }

    /**
     * @return array{
     *     TenantRecord,
     *     OrganizationRecord,
     *     VisitRecord,
     *     VisitVehicleRecord
     * }
     */
    private function createVisitContext(): array
    {
        $tenant = TenantRecord::query()->create([
            'name' => 'GRUPO VEÍCULO',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE VEÍCULO LTDA',
            'display_name' => 'UNIDADE VEÍCULO',
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE COM VEÍCULO',
            'status' => VisitorStatus::Active,
        ]);

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => VisitStatus::Scheduled,
            'purpose' => 'ENTREGA OPERACIONAL',
            'expected_start_at' => now()->addHour(),
        ]);

        $vehicle = VisitVehicleRecord::query()->create([
            'visit_id' => $visit->id,
            'plate' => 'ABC1D23',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'color' => 'Branca',
            'entry_authorized' => false,
        ]);

        return [
            $tenant,
            $organization,
            $visit,
            $vehicle,
        ];
    }

    private function authorizerRole(): Role
    {
        Permission::findOrCreate(
            'AuthorizeVehicleEntry:VisitRecord',
            'web'
        );

        $role = Role::findOrCreate(
            'vehicle_notification_authorizer_test',
            'web'
        );

        $role->syncPermissions([
            'AuthorizeVehicleEntry:VisitRecord',
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
     * @param  array<int, string>  $expectedFragments
     */
    private function assertNotificationExistsFor(
        User $user,
        array $expectedFragments
    ): void {
        $notification = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->first();

        $this->assertNotNull($notification);

        $decodedData = json_decode(
            (string) $notification->data,
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $searchableData = json_encode(
            $decodedData,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
        );

        foreach ($expectedFragments as $fragment) {
            $this->assertStringContainsString(
                $fragment,
                $searchableData
            );
        }
    }

    private function assertNotificationDoesNotExistFor(
        User $user
    ): void {
        $this->assertFalse(
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $user->id)
                ->exists()
        );
    }
}
