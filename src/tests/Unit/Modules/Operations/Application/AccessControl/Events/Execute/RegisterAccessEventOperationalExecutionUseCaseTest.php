<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\Execute;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\AccessControl\Events\Execute\RegisterAccessEventOperationalExecutionCommand;
use App\Modules\Operations\Application\AccessControl\Events\Execute\RegisterAccessEventOperationalExecutionException;
use App\Modules\Operations\Application\AccessControl\Events\Execute\RegisterAccessEventOperationalExecutionResult;
use App\Modules\Operations\Application\AccessControl\Events\Execute\RegisterAccessEventOperationalExecutionUseCase;
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
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalExecutionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegisterAccessEventOperationalExecutionUseCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config()->set(
            'access_control.mode',
            AccessControlMode::Observer->value
        );

        config()->set(
            'access_control.automatic_visit_operations_enabled',
            false
        );
    }

    public function test_it_registers_a_blocked_attempt_when_automatic_execution_is_disabled(): void
    {
        $context = $this->createContext();

        $result = $this->execute(
            $context['decision']
        );

        $visit = $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->status
        );

        $this->assertSame(
            'automatic_execution_disabled',
            $result->reasonCode
        );

        $this->assertSame(
            1,
            $result->attemptNumber
        );

        $this->assertFalse(
            $result->automaticExecutionAllowed
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

    public function test_it_is_idempotent_when_the_blocked_context_does_not_change(): void
    {
        $context = $this->createContext();

        $first = $this->execute(
            $context['decision']
        );

        $second = $this->execute(
            $context['decision']
        );

        $this->assertFalse(
            $first->duplicate
        );

        $this->assertTrue(
            $second->duplicate
        );

        $this->assertSame(
            $first->executionId,
            $second->executionId
        );

        $this->assertSame(
            1,
            AccessEventOperationalExecutionRecord::query()
                ->where(
                    'operational_decision_id',
                    $context['decision']->id
                )
                ->count()
        );
    }

    public function test_it_blocks_a_stale_operational_decision(): void
    {
        $context = $this->createContext();

        AccessEventOperationalDecisionRecord::query()
            ->create([
                'access_event_id' => $context['event']->id,
                'tenant_id' => $context['tenant']->id,
                'organization_id' => $context['organization']->id,
                'visitor_id' => $context['visitor']->id,
                'visit_id' => $context['visit']->id,
                'version' => 2,
                'decision' => AccessEventOperationalDecision::NoAction,
                'reason_code' => 'visit_already_in_progress',
                'reason_message' => 'A decisão operacional foi atualizada.',
                'automatic_execution_enabled' => false,
                'decided_at' => now(),
            ]);

        $result = $this->execute(
            $context['decision']
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->status
        );

        $this->assertSame(
            'stale_operational_decision',
            $result->reasonCode
        );
    }

    public function test_it_skips_a_decision_that_is_not_an_operational_candidate(): void
    {
        $context = $this->createContext(
            decision: AccessEventOperationalDecision::NoAction,
        );

        $result = $this->execute(
            $context['decision']
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Skipped,
            $result->status
        );

        $this->assertSame(
            'decision_not_executable',
            $result->reasonCode
        );
    }

    public function test_it_registers_a_pending_attempt_without_executing_the_visit(): void
    {
        config()->set(
            'access_control.mode',
            AccessControlMode::Primary->value
        );

        config()->set(
            'access_control.automatic_visit_operations_enabled',
            true
        );

        $context = $this->createContext(
            automaticExecutionEnabled: true,
        );

        $result = $this->execute(
            $context['decision']
        );

        $visit = $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Pending,
            $result->status
        );

        $this->assertSame(
            'execution_ready_for_controlled_processing',
            $result->reasonCode
        );

        $this->assertTrue(
            $result->automaticExecutionAllowed
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

    public function test_it_blocks_when_the_event_is_no_longer_processed(): void
    {
        $this->enableAutomaticExecution();

        $context = $this->createContext(
            automaticExecutionEnabled: true,
        );

        $context['event']->forceFill([
            'status' => AccessEventStatus::PendingAssociation,
        ])->save();

        $result = $this->execute(
            $context['decision']
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->status
        );

        $this->assertSame(
            'execution_event_not_processed',
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

    public function test_it_blocks_when_the_event_association_changed_after_the_decision(): void
    {
        $this->enableAutomaticExecution();

        $context = $this->createContext(
            automaticExecutionEnabled: true,
        );

        AccessEventRecord::query()
            ->whereKey(
                $context['event']->id
            )
            ->update([
                'visit_id' => null,
            ]);

        $result = $this->execute(
            $context['decision']
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->status
        );

        $this->assertSame(
            'execution_event_association_changed',
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

    public function test_it_blocks_when_the_event_direction_no_longer_matches_the_decision(): void
    {
        $this->enableAutomaticExecution();

        $context = $this->createContext(
            automaticExecutionEnabled: true,
        );

        $context['event']->forceFill([
            'direction' => AccessEventDirection::Exit,
        ])->save();

        $result = $this->execute(
            $context['decision']
        );

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
            $context['visit']->fresh()?->status
        );

        $this->assertNull(
            $context['visit']->fresh()?->checked_in_at
        );

        Http::assertSentCount(0);
    }

    public function test_it_blocks_when_the_visitor_became_inactive(): void
    {
        $this->enableAutomaticExecution();

        $context = $this->createContext(
            automaticExecutionEnabled: true,
        );

        $context['visitor']->forceFill([
            'status' => VisitorStatus::Inactive,
        ])->save();

        $result = $this->execute(
            $context['decision']
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->status
        );

        $this->assertSame(
            'execution_visitor_inactive',
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

    public function test_it_blocks_when_the_entry_visitor_photo_was_removed(): void
    {
        $this->enableAutomaticExecution();

        $context = $this->createContext(
            automaticExecutionEnabled: true,
        );

        $context['visitor']->forceFill([
            'photo_path' => null,
            'photo_uploaded_at' => null,
        ])->save();

        $result = $this->execute(
            $context['decision']
        );

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
            $context['visit']->fresh()?->status
        );

        $this->assertNull(
            $context['visit']->fresh()?->checked_in_at
        );

        Http::assertSentCount(0);
    }

    public function test_it_blocks_when_the_visit_status_changed_after_the_decision(): void
    {
        $this->enableAutomaticExecution();

        $context = $this->createContext(
            automaticExecutionEnabled: true,
        );

        $context['visit']->forceFill([
            'status' => VisitStatus::InProgress,
        ])->save();

        $result = $this->execute(
            $context['decision']
        );

        $visit = $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $result->status
        );

        $this->assertSame(
            'execution_visit_status_changed',
            $result->reasonCode
        );

        $this->assertSame(
            VisitStatus::InProgress,
            $visit?->status
        );

        $this->assertNull(
            $visit?->checked_in_at
        );

        Http::assertSentCount(0);
    }

    public function test_it_reports_a_decision_that_does_not_exist(): void
    {
        $this->expectException(
            RegisterAccessEventOperationalExecutionException::class
        );

        $this->expectExceptionMessage(
            'A decisão operacional informada não foi encontrada.'
        );

        app(
            RegisterAccessEventOperationalExecutionUseCase::class
        )->execute(
            new RegisterAccessEventOperationalExecutionCommand(
                decisionId: (string) Str::uuid(),
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
        AccessEventOperationalDecisionRecord $decision
    ): RegisterAccessEventOperationalExecutionResult {
        return app(
            RegisterAccessEventOperationalExecutionUseCase::class
        )->execute(
            new RegisterAccessEventOperationalExecutionCommand(
                decisionId: $decision->id,
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
     *     decision: AccessEventOperationalDecisionRecord
     * }
     */
    private function createContext(
        AccessEventOperationalDecision $decision =
            AccessEventOperationalDecision::CheckInCandidate,
        bool $automaticExecutionEnabled = false,
    ): array {
        $tenant = TenantRecord::query()->create([
            'name' => 'GRUPO DEMONSTRAÇÃO',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE DEMONSTRAÇÃO LTDA',
                'display_name' => 'UNIDADE DEMONSTRAÇÃO',
            ]);

        $device = AccessDeviceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => 'FAC-SIM-ENT-01',
            'name' => 'Facial simulado entrada',
            'provider' => 'simulator',
            'direction' => AccessDeviceDirection::Entry,
            'status' => AccessDeviceStatus::Active,
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'Pessoa Visitante Sintética',
            'status' => VisitorStatus::Active,
            'external_source' => 'simulator',
            'external_id' => 'synthetic-person-execution-use-case',
            'photo_disk' => 'local',
            'photo_path' => 'visitors/synthetic/execution-use-case.jpg',
            'photo_uploaded_at' => now(),
        ]);

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'status' => VisitStatus::Authorized,
            'purpose' => 'Visita sintética',
            'expected_start_at' => now()->addHour(),
        ]);

        $event = AccessEventRecord::query()->create([
            'access_device_id' => $device->id,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'visit_id' => $visit->id,
            'external_event_id' => 'synthetic-execution-use-case-event',
            'external_person_id' => $visitor->external_id,
            'event_type' => 'face_recognition',
            'direction' => AccessEventDirection::Entry,
            'occurred_at' => now(),
            'status' => AccessEventStatus::Processed,
            'result_code' => 'association_completed',
            'processed_at' => now(),
            'processing_attempts' => 1,
        ]);

        $reasonCode = match ($decision) {
            AccessEventOperationalDecision::CheckInCandidate => 'check_in_candidate',
            AccessEventOperationalDecision::CheckOutCandidate => 'check_out_candidate',
            AccessEventOperationalDecision::ManualReview => 'manual_review',
            AccessEventOperationalDecision::NoAction => 'no_action',
        };

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
                    'reason_code' => $reasonCode,
                    'reason_message' => 'Decisão operacional sintética.',
                    'automatic_execution_enabled' => $automaticExecutionEnabled,
                    'decided_at' => now(),
                ]);

        return [
            'tenant' => $tenant,
            'organization' => $organization,
            'device' => $device,
            'visitor' => $visitor,
            'visit' => $visit,
            'event' => $event,
            'decision' => $decisionRecord,
        ];
    }
}
