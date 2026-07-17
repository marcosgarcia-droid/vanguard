<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation\ContinueManuallyAssociatedAccessEventFlowCommand;
use App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation\ContinueManuallyAssociatedAccessEventFlowException;
use App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation\ContinueManuallyAssociatedAccessEventFlowResult;
use App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation\ContinueManuallyAssociatedAccessEventFlowUseCase;
use App\Modules\Operations\Application\AccessControl\Events\ManualAssociate\ManualAssociateAccessEventCommand;
use App\Modules\Operations\Application\AccessControl\Events\ManualAssociate\ManualAssociateAccessEventResult;
use App\Modules\Operations\Application\AccessControl\Events\ManualAssociate\ManualAssociateAccessEventUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessControlMode;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualAssociationRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContinueManuallyAssociatedAccessEventFlowUseCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config()->set(
            'access_control.simulator_enabled',
            true
        );

        config()->set(
            'access_control.mode',
            AccessControlMode::Observer->value
        );

        config()->set(
            'access_control.automatic_visit_operations_enabled',
            false
        );
    }

    public function test_it_resolves_from_the_container(): void
    {
        $this->assertInstanceOf(
            ContinueManuallyAssociatedAccessEventFlowUseCase::class,
            app(
                ContinueManuallyAssociatedAccessEventFlowUseCase::class
            )
        );
    }

    public function test_it_continues_a_complete_manual_association_in_observer_mode(): void
    {
        $scenario = $this->createScenario();

        $association = $this->associate(
            scenario: $scenario,
            visitId: $scenario['visit']->id,
        );

        $result = $this->continue($scenario);
        $visit = $scenario['visit']->refresh();

        $this->assertSame(
            $association->associationId,
            $result->associationId
        );

        $this->assertSame(
            AccessEventStatus::Processed,
            $result->flow->processing->status
        );

        $this->assertSame(
            'manual_association_completed',
            $result->flow->processing->resultCode
        );

        $this->assertTrue(
            $result->flow->processing->duplicate
        );

        $this->assertSame(
            AccessEventOperationalDecision::CheckInCandidate,
            $result->flow->decision->decision
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->flow->registration->status
        );

        $this->assertSame(
            'automatic_execution_disabled',
            $result->flow->registration->reasonCode
        );

        $this->assertNull(
            $result->flow->execution
        );

        $this->assertSame(
            VisitStatus::Authorized,
            $visit->status
        );

        $this->assertNull(
            $visit->checked_in_at
        );

        $this->assertSame(
            1,
            $scenario['event']
                ->operationalDecisions()
                ->count()
        );

        $this->assertSame(
            1,
            $scenario['event']
                ->operationalExecutions()
                ->count()
        );

        Http::assertSentCount(0);
    }

    public function test_it_executes_only_once_when_the_continuation_is_repeated(): void
    {
        $scenario = $this->createScenario();

        $this->associate(
            scenario: $scenario,
            visitId: $scenario['visit']->id,
        );

        $this->enableAutomaticExecution();

        $first = $this->continue($scenario);
        $firstVisit = $scenario['visit']->refresh();

        $firstCheckedInAt =
            $firstVisit->checked_in_at?->format(
                'Y-m-d H:i:s'
            );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Executed,
            $first->flow->execution?->status
        );

        $this->assertSame(
            VisitStatus::InProgress,
            $firstVisit->status
        );

        $this->assertNotNull(
            $firstCheckedInAt
        );

        $second = $this->continue($scenario);
        $secondVisit = $scenario['visit']->refresh();

        $this->assertTrue(
            $second->flow->processing->duplicate
        );

        $this->assertSame(
            $firstCheckedInAt,
            $secondVisit->checked_in_at?->format(
                'Y-m-d H:i:s'
            )
        );

        $this->assertSame(
            1,
            $scenario['event']
                ->operationalExecutions()
                ->where(
                    'status',
                    AccessEventOperationalExecutionStatus::Executed->value
                )
                ->count()
        );

        Http::assertSentCount(0);
    }

    public function test_it_rejects_an_incomplete_manual_association(): void
    {
        $scenario = $this->createScenario();

        $this->associate(
            scenario: $scenario,
            visitId: null,
        );

        $this->expectException(
            ContinueManuallyAssociatedAccessEventFlowException::class
        );

        $this->expectExceptionMessage(
            'Somente eventos processados por uma associação manual completa podem continuar por este fluxo.'
        );

        try {
            $this->continue($scenario);
        } finally {
            $this->assertSame(
                AccessEventStatus::PendingAssociation,
                $scenario['event']->refresh()->status
            );

            $this->assertSame(
                0,
                $scenario['event']
                    ->operationalDecisions()
                    ->count()
            );

            $this->assertSame(
                0,
                $scenario['event']
                    ->operationalExecutions()
                    ->count()
            );

            Http::assertSentCount(0);
        }
    }

    public function test_it_rejects_an_event_processed_without_manual_association(): void
    {
        $scenario = $this->createScenario();

        $scenario['event']
            ->forceFill([
                'visitor_id' => $scenario['visitor']->id,
                'visit_id' => $scenario['visit']->id,
                'status' => AccessEventStatus::Processed,
                'result_code' => 'association_completed',
                'result_message' => 'Associação automática sintética.',
                'processed_at' => now(),
            ])
            ->saveQuietly();

        $this->expectException(
            ContinueManuallyAssociatedAccessEventFlowException::class
        );

        $this->expectExceptionMessage(
            'Somente eventos processados por uma associação manual completa podem continuar por este fluxo.'
        );

        try {
            $this->continue($scenario);
        } finally {
            $this->assertSame(
                0,
                AccessEventManualAssociationRecord::query()
                    ->count()
            );

            $this->assertSame(
                0,
                $scenario['event']
                    ->operationalDecisions()
                    ->count()
            );

            Http::assertSentCount(0);
        }
    }

    public function test_it_rejects_a_context_that_no_longer_matches_the_ledger(): void
    {
        $scenario = $this->createScenario();

        $this->associate(
            scenario: $scenario,
            visitId: $scenario['visit']->id,
        );

        $otherVisit = $this->createVisit(
            visitor: $scenario['visitor'],
            organization: $scenario['organization'],
            status: VisitStatus::Authorized,
            purpose: 'OUTRA VISITA SINTÉTICA A5',
        );

        $scenario['event']
            ->forceFill([
                'visit_id' => $otherVisit->id,
            ])
            ->saveQuietly();

        $this->expectException(
            ContinueManuallyAssociatedAccessEventFlowException::class
        );

        $this->expectExceptionMessage(
            'O contexto atual do evento não corresponde à associação manual completa registrada.'
        );

        try {
            $this->continue($scenario);
        } finally {
            $this->assertSame(
                0,
                $scenario['event']
                    ->operationalDecisions()
                    ->count()
            );

            Http::assertSentCount(0);
        }
    }

    public function test_it_rejects_an_operator_without_reprocess_permission(): void
    {
        $scenario = $this->createScenario();

        $this->associate(
            scenario: $scenario,
            visitId: $scenario['visit']->id,
        );

        $scenario['operator']->revokePermissionTo(
            'ReprocessFlow:AccessEventRecord'
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->expectException(
            ContinueManuallyAssociatedAccessEventFlowException::class
        );

        $this->expectExceptionMessage(
            'O operador não possui autorização para continuar o fluxo deste evento.'
        );

        try {
            $this->continue($scenario);
        } finally {
            $this->assertSame(
                0,
                $scenario['event']
                    ->operationalDecisions()
                    ->count()
            );

            $this->assertSame(
                0,
                $scenario['event']
                    ->operationalExecutions()
                    ->count()
            );

            Http::assertSentCount(0);
        }
    }

    private function enableAutomaticExecution(): void
    {
        config()->set(
            'access_control.mode',
            AccessControlMode::Primary->value
        );

        config()->set(
            'access_control.automatic_visit_operations_enabled',
            true
        );
    }

    /**
     * @param array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     event: AccessEventRecord,
     *     visitor: VisitorRecord,
     *     visit: VisitRecord,
     *     operator: User
     * } $scenario
     */
    private function associate(
        array $scenario,
        ?string $visitId,
    ): ManualAssociateAccessEventResult {
        return app(
            ManualAssociateAccessEventUseCase::class
        )->execute(
            new ManualAssociateAccessEventCommand(
                eventId: $scenario['event']->id,
                visitorId: $scenario['visitor']->id,
                visitId: $visitId,
                operatorUserId: $scenario['operator']->id,
                reason: 'Identidade conferida manualmente para o teste do A5.',
                idempotencyKey: (string) Str::uuid(),
            )
        );
    }

    /**
     * @param array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     event: AccessEventRecord,
     *     visitor: VisitorRecord,
     *     visit: VisitRecord,
     *     operator: User
     * } $scenario
     */
    private function continue(
        array $scenario
    ): ContinueManuallyAssociatedAccessEventFlowResult {
        return app(
            ContinueManuallyAssociatedAccessEventFlowUseCase::class
        )->execute(
            new ContinueManuallyAssociatedAccessEventFlowCommand(
                eventId: $scenario['event']->id,
                operatorUserId: $scenario['operator']->id,
            )
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     event: AccessEventRecord,
     *     visitor: VisitorRecord,
     *     visit: VisitRecord,
     *     operator: User
     * }
     */
    private function createScenario(): array
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO CONTINUAÇÃO MANUAL A5',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE CONTINUAÇÃO MANUAL A5 LTDA',
                'display_name' => 'UNIDADE CONTINUAÇÃO MANUAL A5',
                'unit_code' => 'A5-01',
            ]);

        $device = AccessDeviceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => 'FAC-A5-01',
            'name' => 'LEITOR CONTINUAÇÃO MANUAL A5',
            'provider' => 'simulator',
            'direction' => AccessDeviceDirection::Entry,
            'status' => AccessDeviceStatus::Active,
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE CONTINUAÇÃO MANUAL A5',
            'status' => VisitorStatus::Active,
            'photo_disk' => 'local',
            'photo_path' => 'visitors/synthetic/a5.jpg',
            'photo_uploaded_at' => now(),
        ]);

        $visit = $this->createVisit(
            visitor: $visitor,
            organization: $organization,
            status: VisitStatus::Authorized,
            purpose: 'VALIDAÇÃO CONTINUAÇÃO MANUAL A5',
        );

        $event = AccessEventRecord::query()->create([
            'access_device_id' => $device->id,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'external_event_id' => 'a5-'.Str::uuid(),
            'event_type' => 'face_recognition',
            'direction' => AccessEventDirection::Entry,
            'occurred_at' => '2026-07-16 14:30:00',
            'status' => AccessEventStatus::PendingAssociation,
            'received_at' => '2026-07-16 14:30:00',
            'processing_attempts' => 1,
        ]);

        $associatePermission = Permission::findOrCreate(
            'AssociateManually:AccessEventRecord',
            'web'
        );

        $reprocessPermission = Permission::findOrCreate(
            'ReprocessFlow:AccessEventRecord',
            'web'
        );

        $operator = User::factory()->create([
            'name' => 'OPERADOR CONTINUAÇÃO MANUAL A5',
        ]);

        $operator->givePermissionTo([
            $associatePermission,
            $reprocessPermission,
        ]);

        $operator->organizations()->attach(
            $organization->id,
            [
                'role' => 'operator',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return [
            'tenant' => $tenant,
            'organization' => $organization,
            'event' => $event,
            'visitor' => $visitor,
            'visit' => $visit,
            'operator' => $operator,
        ];
    }

    private function createVisit(
        VisitorRecord $visitor,
        OrganizationRecord $organization,
        VisitStatus $status,
        string $purpose,
    ): VisitRecord {
        return VisitRecord::query()->create([
            'tenant_id' => $organization->tenant_id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => $status,
            'purpose' => $purpose,
            'expected_start_at' => '2026-07-16 14:00:00',
            'expected_end_at' => '2026-07-16 16:00:00',
        ]);
    }
}
