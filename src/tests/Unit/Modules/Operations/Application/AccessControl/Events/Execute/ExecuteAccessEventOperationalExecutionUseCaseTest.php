<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\Execute;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\AccessControl\Events\Execute\ExecuteAccessEventOperationalExecutionCommand;
use App\Modules\Operations\Application\AccessControl\Events\Execute\ExecuteAccessEventOperationalExecutionException;
use App\Modules\Operations\Application\AccessControl\Events\Execute\ExecuteAccessEventOperationalExecutionResult;
use App\Modules\Operations\Application\AccessControl\Events\Execute\ExecuteAccessEventOperationalExecutionUseCase;
use App\Modules\Operations\Application\AccessControl\Events\Execute\RegisterAccessEventOperationalExecutionCommand;
use App\Modules\Operations\Application\AccessControl\Events\Execute\RegisterAccessEventOperationalExecutionUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessControlMode;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionSource;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalExecutionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ExecuteAccessEventOperationalExecutionUseCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->enableAutomaticExecution();
    }

    public function test_it_executes_automatic_check_in_without_a_human_operator(): void
    {
        $context = $this->createPendingContext(
            direction: AccessEventDirection::Entry
        );

        $result = $this->execute(
            $context['execution']
        );

        $visit = $context['visit']->fresh();
        $execution = $context['execution']->fresh();

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Executed,
            $result->status
        );

        $this->assertSame(
            'automatic_check_in_executed',
            $result->reasonCode
        );

        $this->assertSame(
            VisitStatus::Authorized,
            $result->visitStatusBefore
        );

        $this->assertSame(
            VisitStatus::InProgress,
            $result->visitStatusAfter
        );

        $this->assertFalse($result->duplicate);

        $this->assertSame(
            VisitStatus::InProgress,
            $visit?->status
        );

        $this->assertSame(
            '2026-07-15 10:00:00',
            $visit?->checked_in_at?->format(
                'Y-m-d H:i:s'
            )
        );

        $this->assertNull($visit?->checked_in_by);

        /*
         * Chegada e conferência de identidade continuam sendo
         * operações humanas independentes.
         */
        $this->assertNull($visit?->arrived_at);
        $this->assertNull($visit?->arrived_by);
        $this->assertNull($visit?->identity_verified_at);
        $this->assertNull($visit?->identity_verified_by);

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Executed,
            $execution?->status
        );

        $this->assertNull(
            $execution?->operator_user_id
        );

        $this->assertNotNull(
            $execution?->completed_at
        );

        Http::assertSentCount(0);
    }

    public function test_it_executes_automatic_check_out_without_a_human_operator(): void
    {
        $context = $this->createPendingContext(
            direction: AccessEventDirection::Exit
        );

        $result = $this->execute(
            $context['execution']
        );

        $visit = $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Executed,
            $result->status
        );

        $this->assertSame(
            'automatic_check_out_executed',
            $result->reasonCode
        );

        $this->assertSame(
            VisitStatus::InProgress,
            $result->visitStatusBefore
        );

        $this->assertSame(
            VisitStatus::Completed,
            $result->visitStatusAfter
        );

        $this->assertSame(
            VisitStatus::Completed,
            $visit?->status
        );

        $this->assertSame(
            '2026-07-15 11:00:00',
            $visit?->checked_out_at?->format(
                'Y-m-d H:i:s'
            )
        );

        $this->assertNull($visit?->checked_out_by);

        $this->assertSame(
            '2026-07-15 09:00:00',
            $visit?->checked_in_at?->format(
                'Y-m-d H:i:s'
            )
        );

        Http::assertSentCount(0);
    }

    public function test_it_is_idempotent_after_an_automatic_execution(): void
    {
        $context = $this->createPendingContext(
            direction: AccessEventDirection::Entry
        );

        $first = $this->execute(
            $context['execution']
        );

        $firstTimestamp =
            $context['visit']
                ->fresh()
                ?->checked_in_at
                ?->format('Y-m-d H:i:s');

        $second = $this->execute(
            $context['execution']
        );

        $this->assertFalse($first->duplicate);
        $this->assertTrue($second->duplicate);

        $this->assertSame(
            $first->executionId,
            $second->executionId
        );

        $this->assertSame(
            $firstTimestamp,
            $context['visit']
                ->fresh()
                ?->checked_in_at
                ?->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            1,
            AccessEventOperationalExecutionRecord::query()
                ->count()
        );

        Http::assertSentCount(0);
    }

    public function test_it_blocks_when_runtime_was_disabled_after_registration(): void
    {
        $context = $this->createPendingContext(
            direction: AccessEventDirection::Entry
        );

        config()->set(
            'access_control.mode',
            AccessControlMode::Observer->value
        );

        config()->set(
            'access_control.automatic_visit_operations_enabled',
            false
        );

        $result = $this->execute(
            $context['execution']
        );

        $visit = $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->status
        );

        $this->assertSame(
            'automatic_execution_disabled_at_execution',
            $result->reasonCode
        );

        $this->assertSame(
            VisitStatus::Authorized,
            $visit?->status
        );

        $this->assertNull($visit?->checked_in_at);

        Http::assertSentCount(0);
    }

    public function test_it_blocks_a_pending_attempt_with_an_unexpected_reason(): void
    {
        $context = $this->createPendingContext(
            direction: AccessEventDirection::Entry
        );

        $context['execution']->forceFill([
            'reason_code' => 'unexpected_pending_reason',
        ])->save();

        $result = $this->execute(
            $context['execution']
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->status
        );

        $this->assertSame(
            'execution_attempt_not_pending',
            $result->reasonCode
        );

        $this->assertSame(
            VisitStatus::Authorized,
            $context['visit']->fresh()?->status
        );

        $this->assertNull(
            $context['visit']->fresh()?->checked_in_at
        );

        Http::assertSentCount(0);
    }

    public function test_it_blocks_when_the_attempt_snapshot_changed(): void
    {
        $context = $this->createPendingContext(
            direction: AccessEventDirection::Entry
        );

        $context['execution']->forceFill([
            'visit_status_before' => VisitStatus::Draft->value,
            'visit_status_after' => VisitStatus::Draft->value,
        ])->save();

        $result = $this->execute(
            $context['execution']
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->status
        );

        $this->assertSame(
            'execution_snapshot_mismatch',
            $result->reasonCode
        );

        $this->assertSame(
            VisitStatus::Authorized,
            $context['visit']->fresh()?->status
        );

        $this->assertNull(
            $context['visit']->fresh()?->checked_in_at
        );

        Http::assertSentCount(0);
    }

    public function test_it_rolls_back_the_visit_and_marks_the_attempt_failed_on_an_unexpected_error(): void
    {
        $context = $this->createPendingContext(
            direction: AccessEventDirection::Entry
        );

        $eventName =
            'eloquent.updated: '
            .AccessEventOperationalExecutionRecord::class;

        Event::listen(
            $eventName,
            static function (
                AccessEventOperationalExecutionRecord $execution
            ): void {
                if (
                    $execution->status
                    === AccessEventOperationalExecutionStatus::Executed
                ) {
                    throw new RuntimeException(
                        'Falha sintética após a alteração da visita.'
                    );
                }
            }
        );

        try {
            $this->execute(
                $context['execution']
            );

            $this->fail(
                'A execução deveria ter lançado uma exceção controlada.'
            );
        } catch (
            ExecuteAccessEventOperationalExecutionException $exception
        ) {
            $this->assertSame(
                'Não foi possível executar a tentativa operacional.',
                $exception->getMessage()
            );
        } finally {
            Event::forget($eventName);
        }

        $visit = $context['visit']->fresh();
        $execution = $context['execution']->fresh();

        $this->assertSame(
            VisitStatus::Authorized,
            $visit?->status
        );

        $this->assertNull($visit?->checked_in_at);
        $this->assertNull($visit?->checked_in_by);

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Failed,
            $execution?->status
        );

        $this->assertSame(
            'execution_unexpected_failure',
            $execution?->reason_code
        );

        $this->assertNotNull(
            $execution?->completed_at
        );

        Http::assertSentCount(0);
    }

    public function test_it_blocks_check_out_when_the_check_in_timestamp_is_missing(): void
    {
        $context = $this->createPendingContext(
            direction: AccessEventDirection::Exit
        );

        $context['visit']->forceFill([
            'checked_in_at' => null,
        ])->save();

        $result = $this->execute(
            $context['execution']
        );

        $visit = $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->status
        );

        $this->assertSame(
            'execution_check_in_timestamp_missing',
            $result->reasonCode
        );

        $this->assertSame(
            VisitStatus::InProgress,
            $visit?->status
        );

        $this->assertNull(
            $visit?->checked_out_at
        );

        Http::assertSentCount(0);
    }

    public function test_it_blocks_a_check_out_event_that_occurred_before_check_in(): void
    {
        $context = $this->createPendingContext(
            direction: AccessEventDirection::Exit
        );

        $context['visit']->forceFill([
            'checked_in_at' => new DateTimeImmutable(
                '2026-07-15 12:00:00'
            ),
        ])->save();

        $result = $this->execute(
            $context['execution']
        );

        $visit = $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->status
        );

        $this->assertSame(
            'execution_event_before_check_in',
            $result->reasonCode
        );

        $this->assertSame(
            VisitStatus::InProgress,
            $visit?->status
        );

        $this->assertNull(
            $visit?->checked_out_at
        );

        $this->assertSame(
            '2026-07-15 12:00:00',
            $visit
                ?->checked_in_at
                ?->format('Y-m-d H:i:s')
        );

        Http::assertSentCount(0);
    }

    public function test_it_blocks_an_older_pending_attempt_and_executes_only_the_latest_one(): void
    {
        $context = $this->createPendingContext(
            direction: AccessEventDirection::Entry
        );

        $olderExecution = $context['execution'];

        $latestExecution =
            AccessEventOperationalExecutionRecord::query()
                ->create([
                    'operational_decision_id' => $context['decision']->id,
                    'access_event_id' => $context['event']->id,
                    'tenant_id' => $context['tenant']->id,
                    'organization_id' => $context['organization']->id,
                    'visitor_id' => $context['visitor']->id,
                    'visit_id' => $context['visit']->id,
                    'operator_user_id' => null,
                    'attempt_number' => 2,
                    'source' => AccessEventOperationalExecutionSource::Automatic,
                    'status' => AccessEventOperationalExecutionStatus::Pending,
                    'reason_code' => 'execution_ready_for_controlled_processing',
                    'reason_message' => 'Tentativa sintética mais recente.',
                    'automatic_execution_allowed' => true,
                    'visit_status_before' => VisitStatus::Authorized->value,
                    'visit_status_after' => VisitStatus::Authorized->value,
                    'attempted_at' => now(),
                    'completed_at' => null,
                ]);

        $olderResult = $this->execute(
            $olderExecution
        );

        $visitBeforeLatestExecution =
            $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $olderResult->status
        );

        $this->assertSame(
            'stale_execution_attempt',
            $olderResult->reasonCode
        );

        $this->assertSame(
            VisitStatus::Authorized,
            $visitBeforeLatestExecution?->status
        );

        $this->assertNull(
            $visitBeforeLatestExecution?->checked_in_at
        );

        $latestResult = $this->execute(
            $latestExecution
        );

        $visitAfterLatestExecution =
            $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Executed,
            $latestResult->status
        );

        $this->assertSame(
            'automatic_check_in_executed',
            $latestResult->reasonCode
        );

        $this->assertSame(
            VisitStatus::InProgress,
            $visitAfterLatestExecution?->status
        );

        $this->assertSame(
            '2026-07-15 10:00:00',
            $visitAfterLatestExecution
                ?->checked_in_at
                ?->format('Y-m-d H:i:s')
        );

        $this->assertNull(
            $visitAfterLatestExecution?->checked_in_by
        );

        $this->assertSame(
            2,
            AccessEventOperationalExecutionRecord::query()
                ->count()
        );

        Http::assertSentCount(0);
    }

    public function test_it_rejects_an_empty_execution_identifier(): void
    {
        $this->expectException(
            ExecuteAccessEventOperationalExecutionException::class
        );

        $this->expectExceptionMessage(
            'O identificador da tentativa operacional é obrigatório.'
        );

        app(
            ExecuteAccessEventOperationalExecutionUseCase::class
        )->execute(
            new ExecuteAccessEventOperationalExecutionCommand(
                executionId: '   ',
            )
        );
    }

    public function test_it_rejects_an_oversized_execution_identifier(): void
    {
        $this->expectException(
            ExecuteAccessEventOperationalExecutionException::class
        );

        $this->expectExceptionMessage(
            'O identificador da tentativa operacional excede o tamanho permitido.'
        );

        app(
            ExecuteAccessEventOperationalExecutionUseCase::class
        )->execute(
            new ExecuteAccessEventOperationalExecutionCommand(
                executionId: str_repeat('x', 37),
            )
        );
    }

    public function test_it_blocks_when_the_operational_decision_became_stale(): void
    {
        $context = $this->createPendingContext(
            direction: AccessEventDirection::Entry
        );

        AccessEventOperationalDecisionRecord::query()
            ->create([
                'access_event_id' => $context['event']->id,
                'tenant_id' => $context['tenant']->id,
                'organization_id' => $context['organization']->id,
                'visitor_id' => $context['visitor']->id,
                'visit_id' => $context['visit']->id,
                'version' => 2,
                'decision' => AccessEventOperationalDecision::CheckInCandidate,
                'reason_code' => 'check_in_candidate',
                'reason_message' => 'Nova versão sintética da decisão.',
                'automatic_execution_enabled' => true,
                'decided_at' => now(),
            ]);

        $result = $this->execute(
            $context['execution']
        );

        $visit = $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->status
        );

        $this->assertSame(
            'stale_operational_decision',
            $result->reasonCode
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

    public function test_it_blocks_when_the_visitor_photo_was_removed_before_check_in(): void
    {
        $context = $this->createPendingContext(
            direction: AccessEventDirection::Entry
        );

        $context['visitor']->forceFill([
            'photo_path' => null,
            'photo_uploaded_at' => null,
        ])->save();

        $result = $this->execute(
            $context['execution']
        );

        $visit = $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->status
        );

        $this->assertSame(
            'execution_visitor_photo_missing',
            $result->reasonCode
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

    public function test_it_blocks_when_the_event_direction_changed_before_execution(): void
    {
        $context = $this->createPendingContext(
            direction: AccessEventDirection::Entry
        );

        $context['event']->forceFill([
            'direction' => AccessEventDirection::Exit,
        ])->save();

        $result = $this->execute(
            $context['execution']
        );

        $visit = $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->status
        );

        $this->assertSame(
            'execution_direction_mismatch',
            $result->reasonCode
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

    public function test_it_reports_an_execution_that_does_not_exist(): void
    {
        $this->expectException(
            ExecuteAccessEventOperationalExecutionException::class
        );

        $this->expectExceptionMessage(
            'A tentativa operacional informada não foi encontrada.'
        );

        app(
            ExecuteAccessEventOperationalExecutionUseCase::class
        )->execute(
            new ExecuteAccessEventOperationalExecutionCommand(
                executionId: (string) Str::uuid(),
            )
        );
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

    private function execute(
        AccessEventOperationalExecutionRecord $execution
    ): ExecuteAccessEventOperationalExecutionResult {
        return app(
            ExecuteAccessEventOperationalExecutionUseCase::class
        )->execute(
            new ExecuteAccessEventOperationalExecutionCommand(
                executionId: $execution->id,
            )
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     device: AccessDeviceRecord,
     *     visitor: VisitorRecord,
     *     visit: VisitRecord,
     *     event: AccessEventRecord,
     *     decision: AccessEventOperationalDecisionRecord,
     *     execution: AccessEventOperationalExecutionRecord
     * }
     */
    private function createPendingContext(
        AccessEventDirection $direction
    ): array {
        $tenant = TenantRecord::query()->create([
            'name' => 'GRUPO EXECUÇÃO AUTOMÁTICA',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE EXECUÇÃO AUTOMÁTICA LTDA',
                'display_name' => 'UNIDADE EXECUÇÃO AUTOMÁTICA',
            ]);

        $deviceDirection =
            $direction === AccessEventDirection::Entry
                ? AccessDeviceDirection::Entry
                : AccessDeviceDirection::Exit;

        $device = AccessDeviceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => $direction === AccessEventDirection::Entry
                    ? 'FAC-SIM-ENT-EXEC'
                    : 'FAC-SIM-SAI-EXEC',
            'name' => 'Facial sintético de execução',
            'provider' => 'simulator',
            'direction' => $deviceDirection,
            'status' => AccessDeviceStatus::Active,
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'Pessoa Visitante Execução Sintética',
            'status' => VisitorStatus::Active,
            'external_source' => 'simulator',
            'external_id' => 'synthetic-person-automatic-execution',
            'photo_disk' => 'local',
            'photo_path' => 'visitors/synthetic/automatic-execution.jpg',
            'photo_uploaded_at' => now(),
        ]);

        $visitStatus =
            $direction === AccessEventDirection::Entry
                ? VisitStatus::Authorized
                : VisitStatus::InProgress;

        $visitAttributes = [
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => $visitStatus,
            'purpose' => 'Visita automática sintética',
            'expected_start_at' => new DateTimeImmutable(
                '2026-07-15 08:00:00'
            ),
        ];

        if ($direction === AccessEventDirection::Exit) {
            $visitAttributes['checked_in_at'] =
                new DateTimeImmutable(
                    '2026-07-15 09:00:00'
                );
        }

        $visit = VisitRecord::query()->create(
            $visitAttributes
        );

        $occurredAt =
            $direction === AccessEventDirection::Entry
                ? new DateTimeImmutable(
                    '2026-07-15 10:00:00'
                )
                : new DateTimeImmutable(
                    '2026-07-15 11:00:00'
                );

        $event = AccessEventRecord::query()->create([
            'access_device_id' => $device->id,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'visit_id' => $visit->id,
            'external_event_id' => $direction === AccessEventDirection::Entry
                    ? 'synthetic-auto-entry-event'
                    : 'synthetic-auto-exit-event',
            'external_person_id' => $visitor->external_id,
            'event_type' => 'face_recognition',
            'direction' => $direction,
            'occurred_at' => $occurredAt,
            'status' => AccessEventStatus::Processed,
            'result_code' => 'association_completed',
            'processed_at' => $occurredAt,
            'processing_attempts' => 1,
        ]);

        $decision =
            $direction === AccessEventDirection::Entry
                ? AccessEventOperationalDecision::CheckInCandidate
                : AccessEventOperationalDecision::CheckOutCandidate;

        $decisionRecord =
            AccessEventOperationalDecisionRecord::query()
                ->create([
                    'access_event_id' => $event->id,
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'visitor_id' => $visitor->id,
                    'visit_id' => $visit->id,
                    'version' => 1,
                    'decision' => $decision,
                    'reason_code' => $decision->value,
                    'reason_message' => 'Decisão automática sintética.',
                    'automatic_execution_enabled' => true,
                    'decided_at' => $occurredAt,
                ]);

        $registration = app(
            RegisterAccessEventOperationalExecutionUseCase::class
        )->execute(
            new RegisterAccessEventOperationalExecutionCommand(
                decisionId: $decisionRecord->id,
            )
        );

        $execution =
            AccessEventOperationalExecutionRecord::query()
                ->findOrFail(
                    $registration->executionId
                );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Pending,
            $execution->status
        );

        return [
            'tenant' => $tenant,
            'organization' => $organization,
            'device' => $device,
            'visitor' => $visitor,
            'visit' => $visit,
            'event' => $event,
            'decision' => $decisionRecord,
            'execution' => $execution,
        ];
    }
}
