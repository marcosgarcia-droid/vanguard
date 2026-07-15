<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
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
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AccessEventOperationalDecisionRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_preserves_a_versioned_audited_operational_decision(): void
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
            'external_id' => 'synthetic-person-decision-001',
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
            'external_event_id' => 'synthetic-decision-event-001',
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
                    'reason_message' => 'Evento elegível para futura operação de entrada.',
                    'automatic_execution_enabled' => false,
                    'decided_at' => now(),
                ]);

        $loaded =
            AccessEventOperationalDecisionRecord::query()
                ->with([
                    'accessEvent',
                    'tenant',
                    'organization',
                    'visitor',
                    'visit',
                ])
                ->findOrFail(
                    $decision->id
                );

        $this->assertSame(
            1,
            $loaded->version
        );

        $this->assertSame(
            AccessEventOperationalDecision::CheckInCandidate,
            $loaded->decision
        );

        $this->assertFalse(
            $loaded->automatic_execution_enabled
        );

        $this->assertTrue(
            $loaded->accessEvent->is($event)
        );

        $this->assertTrue(
            $loaded->visitor->is($visitor)
        );

        $this->assertTrue(
            $loaded->visit->is($visit)
        );

        $this->assertTrue(
            $event->operationalDecisions()
                ->whereKey($decision->id)
                ->exists()
        );

        $activity = Activity::query()
            ->where(
                'subject_type',
                AccessEventOperationalDecisionRecord::class
            )
            ->where(
                'subject_id',
                $decision->id
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
