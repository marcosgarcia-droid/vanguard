<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\Orchestrate;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\AccessControl\Events\Ingest\IngestAccessEventUseCase;
use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowCommand;
use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowException;
use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowResult;
use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowUseCase;
use App\Modules\Operations\Application\AccessControl\Events\Process\ProcessAccessEventException;
use App\Modules\Operations\Domain\AccessControl\AccessControlMode;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Integrations\Simulator\SimulatedAccessEventGenerator;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalExecutionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContinueAccessEventFlowUseCaseTest extends TestCase
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
            ContinueAccessEventFlowUseCase::class,
            app(ContinueAccessEventFlowUseCase::class)
        );
    }

    public function test_it_continues_the_event_without_executing_when_automatic_operations_are_disabled(): void
    {
        $context = $this->createIngestedContext();

        $result = $this->continue(
            $context['event_id']
        );

        $visit = $context['visit']?->fresh();

        $this->assertSame(
            AccessEventStatus::Processed,
            $result->processing->status
        );

        $this->assertSame(
            AccessEventOperationalDecision::CheckInCandidate,
            $result->decision->decision
        );

        $this->assertFalse(
            $result->decision->automaticExecutionEnabled
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->registration->status
        );

        $this->assertSame(
            'automatic_execution_disabled',
            $result->registration->reasonCode
        );

        $this->assertNull(
            $result->execution
        );

        $this->assertSame(
            VisitStatus::Authorized,
            $visit?->status
        );

        $this->assertNull(
            $visit?->checked_in_at
        );

        Http::assertSentCount(0);
    }

    public function test_it_executes_the_complete_internal_flow_when_the_runtime_allows_it(): void
    {
        $context = $this->createIngestedContext();

        $this->enableAutomaticExecution();

        $result = $this->continue(
            $context['event_id']
        );

        $visit = $context['visit']?->fresh();

        $this->assertSame(
            AccessEventStatus::Processed,
            $result->processing->status
        );

        $this->assertSame(
            AccessEventOperationalDecision::CheckInCandidate,
            $result->decision->decision
        );

        $this->assertTrue(
            $result->decision->automaticExecutionEnabled
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Pending,
            $result->registration->status
        );

        $this->assertNotNull(
            $result->execution
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Executed,
            $result->execution?->status
        );

        $this->assertSame(
            'automatic_check_in_executed',
            $result->execution?->reasonCode
        );

        $this->assertSame(
            VisitStatus::InProgress,
            $visit?->status
        );

        $this->assertSame(
            '2026-07-15 14:00:00',
            $visit?->checked_in_at?->format(
                'Y-m-d H:i:s'
            )
        );

        $this->assertNull(
            $visit?->checked_in_by
        );

        $this->assertNull(
            $visit?->arrived_at
        );

        $this->assertNull(
            $visit?->identity_verified_at
        );

        Http::assertSentCount(0);
    }

    public function test_it_resumes_a_blocked_event_after_the_runtime_becomes_primary(): void
    {
        $context = $this->createIngestedContext();

        $first = $this->continue(
            $context['event_id']
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $first->registration->status
        );

        $this->assertSame(
            'automatic_execution_disabled',
            $first->registration->reasonCode
        );

        $this->assertNull(
            $first->execution
        );

        $this->assertSame(
            VisitStatus::Authorized,
            $context['visit']?->fresh()?->status
        );

        $this->enableAutomaticExecution();

        $second = $this->continue(
            $context['event_id']
        );

        $visit = $context['visit']?->fresh();

        $this->assertTrue(
            $second->processing->duplicate
        );

        $this->assertSame(
            2,
            $second->decision->version
        );

        $this->assertSame(
            AccessEventOperationalDecision::CheckInCandidate,
            $second->decision->decision
        );

        $this->assertTrue(
            $second->decision->automaticExecutionEnabled
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Pending,
            $second->registration->status
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Executed,
            $second->execution?->status
        );

        $this->assertSame(
            'automatic_check_in_executed',
            $second->execution?->reasonCode
        );

        $this->assertSame(
            VisitStatus::InProgress,
            $visit?->status
        );

        $this->assertSame(
            '2026-07-15 14:00:00',
            $visit?->checked_in_at?->format(
                'Y-m-d H:i:s'
            )
        );

        $this->assertNull(
            $visit?->checked_in_by
        );

        $this->assertSame(
            2,
            AccessEventOperationalDecisionRecord::query()
                ->count()
        );

        $this->assertSame(
            2,
            AccessEventOperationalExecutionRecord::query()
                ->count()
        );

        Http::assertSentCount(0);
    }

    public function test_it_executes_the_complete_internal_check_out_flow(): void
    {
        $context = $this->createIngestedContext();

        $context['device']
            ->forceFill([
                'direction' => AccessDeviceDirection::Exit,
            ])
            ->save();

        AccessEventRecord::query()
            ->findOrFail(
                $context['event_id']
            )
            ->forceFill([
                'direction' => AccessEventDirection::Exit,
            ])
            ->save();

        $context['visit']
            ?->forceFill([
                'status' => VisitStatus::InProgress,
                'checked_in_by' => null,
                'checked_in_at' => new DateTimeImmutable(
                    '2026-07-15 13:30:00'
                ),
            ])
            ->save();

        $this->enableAutomaticExecution();

        $result = $this->continue(
            $context['event_id']
        );

        $visit = $context['visit']?->fresh();

        $this->assertSame(
            AccessEventStatus::Processed,
            $result->processing->status
        );

        $this->assertSame(
            AccessEventOperationalDecision::CheckOutCandidate,
            $result->decision->decision
        );

        $this->assertTrue(
            $result->decision->automaticExecutionEnabled
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Pending,
            $result->registration->status
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Executed,
            $result->execution?->status
        );

        $this->assertSame(
            'automatic_check_out_executed',
            $result->execution?->reasonCode
        );

        $this->assertSame(
            VisitStatus::Completed,
            $visit?->status
        );

        $this->assertSame(
            '2026-07-15 13:30:00',
            $visit?->checked_in_at?->format(
                'Y-m-d H:i:s'
            )
        );

        $this->assertSame(
            '2026-07-15 14:00:00',
            $visit?->checked_out_at?->format(
                'Y-m-d H:i:s'
            )
        );

        $this->assertNull(
            $visit?->checked_out_by
        );

        Http::assertSentCount(0);
    }

    public function test_it_records_manual_review_and_does_not_call_the_executor_when_association_is_incomplete(): void
    {
        $context = $this->createIngestedContext(
            createVisitorAndVisit: false
        );

        $this->enableAutomaticExecution();

        $result = $this->continue(
            $context['event_id']
        );

        $this->assertSame(
            AccessEventStatus::PendingAssociation,
            $result->processing->status
        );

        $this->assertSame(
            AccessEventOperationalDecision::ManualReview,
            $result->decision->decision
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Skipped,
            $result->registration->status
        );

        $this->assertSame(
            'decision_not_executable',
            $result->registration->reasonCode
        );

        $this->assertNull(
            $result->execution
        );

        $this->assertSame(
            1,
            AccessEventOperationalDecisionRecord::query()
                ->count()
        );

        $this->assertSame(
            1,
            AccessEventOperationalExecutionRecord::query()
                ->count()
        );

        Http::assertSentCount(0);
    }

    public function test_reprocessing_after_execution_does_not_execute_check_in_twice(): void
    {
        $context = $this->createIngestedContext();

        $this->enableAutomaticExecution();

        $first = $this->continue(
            $context['event_id']
        );

        $firstCheckedInAt =
            $context['visit']
                ?->fresh()
                ?->checked_in_at
                ?->format('Y-m-d H:i:s');

        $second = $this->continue(
            $context['event_id']
        );

        $visit = $context['visit']?->fresh();

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Executed,
            $first->execution?->status
        );

        $this->assertTrue(
            $second->processing->duplicate
        );

        $this->assertSame(
            AccessEventOperationalDecision::NoAction,
            $second->decision->decision
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Skipped,
            $second->registration->status
        );

        $this->assertNull(
            $second->execution
        );

        $this->assertSame(
            VisitStatus::InProgress,
            $visit?->status
        );

        $this->assertSame(
            $firstCheckedInAt,
            $visit?->checked_in_at?->format(
                'Y-m-d H:i:s'
            )
        );

        $this->assertSame(
            2,
            AccessEventOperationalDecisionRecord::query()
                ->count()
        );

        $this->assertSame(
            2,
            AccessEventOperationalExecutionRecord::query()
                ->count()
        );

        Http::assertSentCount(0);
    }

    public function test_it_rejects_an_empty_event_identifier(): void
    {
        $this->expectException(
            ContinueAccessEventFlowException::class
        );

        $this->expectExceptionMessage(
            'O identificador do evento é obrigatório para continuar o fluxo operacional.'
        );

        app(ContinueAccessEventFlowUseCase::class)
            ->execute(
                new ContinueAccessEventFlowCommand(
                    eventId: '   ',
                )
            );
    }

    public function test_it_wraps_a_missing_event_processing_failure(): void
    {
        try {
            $this->continue(
                (string) Str::uuid()
            );

            $this->fail(
                'A continuação deveria informar que o evento não existe.'
            );
        } catch (
            ContinueAccessEventFlowException $exception
        ) {
            $this->assertSame(
                'Não foi possível processar o evento durante a continuação do fluxo operacional.',
                $exception->getMessage()
            );

            $this->assertInstanceOf(
                ProcessAccessEventException::class,
                $exception->getPrevious()
            );
        }

        Http::assertSentCount(0);
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

    private function continue(
        string $eventId
    ): ContinueAccessEventFlowResult {
        return app(
            ContinueAccessEventFlowUseCase::class
        )->execute(
            new ContinueAccessEventFlowCommand(
                eventId: $eventId,
            )
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     device: AccessDeviceRecord,
     *     visitor: VisitorRecord|null,
     *     visit: VisitRecord|null,
     *     event_id: string
     * }
     */
    private function createIngestedContext(
        bool $createVisitorAndVisit = true
    ): array {
        $tenant = TenantRecord::query()->create([
            'name' => 'GRUPO ORQUESTRAÇÃO SINTÉTICA',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE ORQUESTRAÇÃO SINTÉTICA LTDA',
                'display_name' => 'UNIDADE ORQUESTRAÇÃO SINTÉTICA',
            ]);

        $device = AccessDeviceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => 'FAC-SIM-ORQ-ENT-01',
            'name' => 'Facial sintético orquestração',
            'provider' => 'simulator',
            'direction' => AccessDeviceDirection::Entry,
            'status' => AccessDeviceStatus::Active,
        ]);

        $visitor = null;
        $visit = null;

        $externalPersonId =
            'synthetic-person-orchestration';

        if ($createVisitorAndVisit) {
            $visitor = VisitorRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'full_name' => 'Pessoa Visitante Orquestração Sintética',
                'status' => VisitorStatus::Active,
                'external_source' => 'simulator',
                'external_id' => $externalPersonId,
                'photo_disk' => 'local',
                'photo_path' => 'visitors/synthetic/orchestration.jpg',
                'photo_uploaded_at' => now(),
            ]);

            $visit = VisitRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'visitor_id' => $visitor->id,
                'status' => VisitStatus::Authorized,
                'purpose' => 'Validação sintética da orquestração',
                'expected_start_at' => new DateTimeImmutable(
                    '2026-07-15 13:00:00'
                ),
            ]);
        } else {
            $externalPersonId =
                'synthetic-person-not-registered';
        }

        $command = app(
            SimulatedAccessEventGenerator::class
        )->generate(
            deviceId: $device->id,
            direction: AccessEventDirection::Entry,
            sequence: 7201,
            occurredAt: new DateTimeImmutable(
                '2026-07-15 14:00:00'
            ),
            externalPersonId: $externalPersonId,
        );

        $ingestion = app(
            IngestAccessEventUseCase::class
        )->execute($command);

        return [
            'tenant' => $tenant,
            'organization' => $organization,
            'device' => $device,
            'visitor' => $visitor,
            'visit' => $visit,
            'event_id' => $ingestion->eventId,
        ];
    }
}
