<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AccessEventOperationalExecutionRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_preserves_an_audited_execution_attempt_without_changing_the_visit(): void
    {
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
            'external_id' => 'synthetic-person-execution-001',
            'photo_disk' => 'local',
            'photo_path' => 'visitors/synthetic/execution-person.jpg',
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
            'external_event_id' => 'synthetic-execution-event-001',
            'external_person_id' => $visitor->external_id,
            'event_type' => 'face_recognition',
            'direction' => AccessEventDirection::Entry,
            'occurred_at' => now(),
            'status' => AccessEventStatus::Processed,
            'result_code' => 'association_completed',
            'processed_at' => now(),
            'processing_attempts' => 1,
        ]);

        $decision =
            AccessEventOperationalDecisionRecord::query()
                ->create([
                    'access_event_id' => $event->id,
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'visitor_id' => $visitor->id,
                    'visit_id' => $visit->id,
                    'version' => 1,
                    'decision' => AccessEventOperationalDecision::CheckInCandidate,
                    'reason_code' => 'check_in_candidate',
                    'reason_message' => 'Evento elegível para futura entrada.',
                    'automatic_execution_enabled' => false,
                    'decided_at' => now(),
                ]);

        $execution =
            AccessEventOperationalExecutionRecord::query()
                ->create([
                    'operational_decision_id' => $decision->id,
                    'access_event_id' => $event->id,
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'visitor_id' => $visitor->id,
                    'visit_id' => $visit->id,
                    'operator_user_id' => null,
                    'attempt_number' => 1,
                    'source' => AccessEventOperationalExecutionSource::Automatic,
                    'status' => AccessEventOperationalExecutionStatus::Blocked,
                    'reason_code' => 'automatic_execution_disabled',
                    'reason_message' => 'A execução automática está desativada.',
                    'automatic_execution_allowed' => false,
                    'visit_status_before' => VisitStatus::Authorized->value,
                    'visit_status_after' => VisitStatus::Authorized->value,
                    'attempted_at' => now(),
                    'completed_at' => now(),
                ]);

        $loaded =
            AccessEventOperationalExecutionRecord::query()
                ->with([
                    'operationalDecision',
                    'accessEvent',
                    'visitor',
                    'visit',
                ])
                ->findOrFail($execution->id);

        $this->assertSame(
            1,
            $loaded->attempt_number
        );

        $this->assertSame(
            AccessEventOperationalExecutionSource::Automatic,
            $loaded->source
        );

        $this->assertSame(
            AccessEventOperationalExecutionStatus::Blocked,
            $loaded->status
        );

        $this->assertFalse(
            $loaded->automatic_execution_allowed
        );

        $this->assertTrue(
            $loaded->operationalDecision->is(
                $decision
            )
        );

        $this->assertTrue(
            $loaded->accessEvent->is($event)
        );

        $this->assertTrue(
            $loaded->visit->is($visit)
        );

        $this->assertTrue(
            $decision->executionAttempts()
                ->whereKey($execution->id)
                ->exists()
        );

        $this->assertTrue(
            $event->operationalExecutions()
                ->whereKey($execution->id)
                ->exists()
        );

        $visit->refresh();

        $this->assertSame(
            VisitStatus::Authorized,
            $visit->status
        );

        $this->assertNull(
            $visit->checked_in_at
        );

        $activity = Activity::query()
            ->where(
                'subject_type',
                AccessEventOperationalExecutionRecord::class
            )
            ->where(
                'subject_id',
                $execution->id
            )
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            VisitorRecord::class,
            data_get(
                $activity->properties,
                'vanguard_parent_type'
            )
        );

        $this->assertSame(
            $visitor->id,
            data_get(
                $activity->properties,
                'vanguard_parent_id'
            )
        );
    }
}
