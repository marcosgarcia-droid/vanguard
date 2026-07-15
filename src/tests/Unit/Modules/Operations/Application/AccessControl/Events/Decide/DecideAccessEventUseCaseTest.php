<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\Decide;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\AccessControl\Events\Decide\DecideAccessEventCommand;
use App\Modules\Operations\Application\AccessControl\Events\Decide\DecideAccessEventException;
use App\Modules\Operations\Application\AccessControl\Events\Decide\DecideAccessEventResult;
use App\Modules\Operations\Application\AccessControl\Events\Decide\DecideAccessEventUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessControlMode;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DecideAccessEventUseCaseTest extends TestCase
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

    public function test_it_derives_a_check_in_candidate_without_changing_the_visit(): void
    {
        $context = $this->createContext(
            direction: AccessEventDirection::Entry,
            visitStatus: VisitStatus::Authorized,
        );

        $result = $this->decide(
            $context['event']
        );

        $visit = $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalDecision::CheckInCandidate,
            $result->decision
        );

        $this->assertSame(
            'check_in_candidate',
            $result->reasonCode
        );

        $this->assertSame(
            1,
            $result->version
        );

        $this->assertFalse(
            $result->automaticExecutionEnabled
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

    public function test_it_derives_a_check_out_candidate_without_completing_the_visit(): void
    {
        $context = $this->createContext(
            direction: AccessEventDirection::Exit,
            visitStatus: VisitStatus::InProgress,
        );

        $result = $this->decide(
            $context['event']
        );

        $visit = $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalDecision::CheckOutCandidate,
            $result->decision
        );

        $this->assertSame(
            'check_out_candidate',
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

    public function test_it_requires_manual_review_when_an_entry_visitor_has_no_photo(): void
    {
        $context = $this->createContext(
            direction: AccessEventDirection::Entry,
            visitStatus: VisitStatus::Authorized,
            withPhoto: false,
        );

        $result = $this->decide(
            $context['event']
        );

        $visit = $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalDecision::ManualReview,
            $result->decision
        );

        $this->assertSame(
            'visitor_photo_missing',
            $result->reasonCode
        );

        $this->assertFalse(
            $result->automaticExecutionEnabled
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

    public function test_it_requires_manual_review_for_an_unprocessed_event(): void
    {
        $context = $this->createContext(
            eventStatus: AccessEventStatus::PendingAssociation,
            associateVisit: false,
        );

        $result = $this->decide(
            $context['event']
        );

        $this->assertSame(
            AccessEventOperationalDecision::ManualReview,
            $result->decision
        );

        $this->assertSame(
            'event_not_processed',
            $result->reasonCode
        );

        $this->assertFalse(
            $result->automaticExecutionEnabled
        );
    }

    public function test_it_requires_manual_review_for_an_incomplete_processed_association(): void
    {
        $context = $this->createContext(
            eventStatus: AccessEventStatus::Processed,
            associateVisit: false,
        );

        $result = $this->decide(
            $context['event']
        );

        $this->assertSame(
            AccessEventOperationalDecision::ManualReview,
            $result->decision
        );

        $this->assertSame(
            'incomplete_association',
            $result->reasonCode
        );
    }

    public function test_it_creates_no_action_for_an_ignored_event(): void
    {
        $context = $this->createContext(
            eventStatus: AccessEventStatus::Ignored,
            associateVisitor: false,
            associateVisit: false,
        );

        $result = $this->decide(
            $context['event']
        );

        $this->assertSame(
            AccessEventOperationalDecision::NoAction,
            $result->decision
        );

        $this->assertSame(
            'event_ignored',
            $result->reasonCode
        );
    }

    public function test_it_is_idempotent_when_the_derived_context_does_not_change(): void
    {
        $context = $this->createContext();

        $first = $this->decide(
            $context['event']
        );

        $second = $this->decide(
            $context['event']
        );

        $this->assertFalse(
            $first->duplicate
        );

        $this->assertTrue(
            $second->duplicate
        );

        $this->assertSame(
            $first->decisionId,
            $second->decisionId
        );

        $this->assertSame(
            1,
            $second->version
        );

        $this->assertSame(
            1,
            AccessEventOperationalDecisionRecord::query()
                ->where(
                    'access_event_id',
                    $context['event']->id
                )
                ->count()
        );
    }

    public function test_it_creates_a_new_version_when_the_visit_state_changes(): void
    {
        $context = $this->createContext();

        $first = $this->decide(
            $context['event']
        );

        $context['visit']
            ->forceFill([
                'status' => VisitStatus::InProgress,
                'checked_in_at' => now(),
            ])
            ->saveQuietly();

        $second = $this->decide(
            $context['event']
        );

        $this->assertSame(
            AccessEventOperationalDecision::CheckInCandidate,
            $first->decision
        );

        $this->assertSame(
            AccessEventOperationalDecision::NoAction,
            $second->decision
        );

        $this->assertSame(
            'visit_already_in_progress',
            $second->reasonCode
        );

        $this->assertSame(
            2,
            $second->version
        );

        $this->assertFalse(
            $second->duplicate
        );

        $this->assertSame(
            2,
            AccessEventOperationalDecisionRecord::query()
                ->where(
                    'access_event_id',
                    $context['event']->id
                )
                ->count()
        );
    }

    public function test_primary_mode_records_automatic_execution_as_enabled_without_executing_it(): void
    {
        config()->set(
            'access_control.mode',
            AccessControlMode::Primary->value
        );

        config()->set(
            'access_control.automatic_visit_operations_enabled',
            true
        );

        $context = $this->createContext();

        $result = $this->decide(
            $context['event']
        );

        $visit = $context['visit']->fresh();

        $this->assertSame(
            AccessEventOperationalDecision::CheckInCandidate,
            $result->decision
        );

        $this->assertTrue(
            $result->automaticExecutionEnabled
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

    public function test_it_reports_an_event_that_does_not_exist(): void
    {
        $this->expectException(
            DecideAccessEventException::class
        );

        $this->expectExceptionMessage(
            'O evento de acesso não foi encontrado.'
        );

        app(
            DecideAccessEventUseCase::class
        )->execute(
            new DecideAccessEventCommand(
                eventId: (string) Str::uuid(),
            )
        );
    }

    private function decide(
        AccessEventRecord $event
    ): DecideAccessEventResult {
        return app(
            DecideAccessEventUseCase::class
        )->execute(
            new DecideAccessEventCommand(
                eventId: $event->id,
            )
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     device: AccessDeviceRecord,
     *     visitor: ?VisitorRecord,
     *     visit: ?VisitRecord,
     *     event: AccessEventRecord
     * }
     */
    private function createContext(
        AccessEventDirection $direction =
            AccessEventDirection::Entry,
        AccessEventStatus $eventStatus =
            AccessEventStatus::Processed,
        VisitStatus $visitStatus =
            VisitStatus::Authorized,
        bool $associateVisitor = true,
        bool $associateVisit = true,
        bool $withPhoto = true,
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
            'code' => $direction
                === AccessEventDirection::Entry
                    ? 'FAC-SIM-ENT-01'
                    : 'FAC-SIM-SAI-01',
            'name' => 'Facial simulado',
            'provider' => 'simulator',
            'direction' => $direction
                === AccessEventDirection::Entry
                    ? AccessDeviceDirection::Entry
                    : AccessDeviceDirection::Exit,
            'status' => AccessDeviceStatus::Active,
        ]);

        $visitor = null;
        $visit = null;

        if ($associateVisitor) {
            $visitor = VisitorRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'full_name' => 'Pessoa Visitante Sintética',
                'status' => VisitorStatus::Active,
                'external_source' => 'simulator',
                'external_id' => 'synthetic-person-decision-001',
                'photo_disk' => 'local',
                'photo_path' => $withPhoto
                    ? 'visitors/synthetic/decision-person.jpg'
                    : null,
                'photo_uploaded_at' => $withPhoto
                    ? now()
                    : null,
            ]);
        }

        if (
            $associateVisit
            && $visitor instanceof VisitorRecord
        ) {
            $visit = VisitRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'visitor_id' => $visitor->id,
                'status' => $visitStatus,
                'purpose' => 'Visita sintética',
                'expected_start_at' => now()->addHour(),
            ]);
        }

        $event = AccessEventRecord::query()->create([
            'access_device_id' => $device->id,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $associateVisitor
                    ? $visitor?->id
                    : null,
            'visit_id' => $associateVisit
                    ? $visit?->id
                    : null,
            'external_event_id' => 'synthetic-decision-event-001',
            'external_person_id' => $visitor?->external_id,
            'event_type' => 'face_recognition',
            'direction' => $direction,
            'occurred_at' => now(),
            'status' => $eventStatus,
            'result_code' => $eventStatus
                === AccessEventStatus::Processed
                    ? 'association_completed'
                    : 'pending_association',
            'processed_at' => $eventStatus
                === AccessEventStatus::Processed
                    ? now()
                    : null,
            'processing_attempts' => $eventStatus
                === AccessEventStatus::Processed
                    ? 1
                    : 0,
        ]);

        return compact(
            'tenant',
            'organization',
            'device',
            'visitor',
            'visit',
            'event',
        );
    }
}
