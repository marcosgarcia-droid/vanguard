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
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalExecutionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessEventRecordLatestRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_the_latest_decision_and_execution_using_operational_order(): void
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO RELAÇÕES',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE RELAÇÕES LTDA',
                'display_name' => 'UNIDADE RELAÇÕES',
            ]);

        $device = AccessDeviceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'code' => 'FAC-REL-01',
            'name' => 'Facial relações',
            'provider' => 'simulator',
            'direction' => AccessDeviceDirection::Entry,
            'status' => AccessDeviceStatus::Active,
        ]);

        $event = AccessEventRecord::query()->create([
            'access_device_id' => $device->id,
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'external_event_id' => 'event-relations-001',
            'event_type' => 'face_recognition',
            'direction' => AccessEventDirection::Entry,
            'occurred_at' => new DateTimeImmutable(
                '2026-07-16 08:00:00'
            ),
            'status' => AccessEventStatus::Processed,
        ]);

        $firstDecision =
            $this->createDecision(
                event: $event,
                version: 1,
                decidedAt: '2026-07-16 08:01:00',
            );

        $secondDecision =
            $this->createDecision(
                event: $event,
                version: 2,
                decidedAt: '2026-07-16 08:02:00',
            );

        $this->createExecution(
            event: $event,
            decision: $firstDecision,
            attemptedAt: '2026-07-16 08:01:30',
        );

        $secondExecution =
            $this->createExecution(
                event: $event,
                decision: $secondDecision,
                attemptedAt: '2026-07-16 08:02:30',
            );

        $loaded = AccessEventRecord::query()
            ->with([
                'latestOperationalDecision',
                'latestOperationalExecution',
            ])
            ->findOrFail($event->id);

        $this->assertInstanceOf(
            HasOne::class,
            $event->latestOperationalDecision()
        );

        $this->assertInstanceOf(
            HasOne::class,
            $event->latestOperationalExecution()
        );

        $this->assertTrue(
            $loaded->latestOperationalDecision
                ->is($secondDecision)
        );

        $this->assertTrue(
            $loaded->latestOperationalExecution
                ->is($secondExecution)
        );
    }

    private function createDecision(
        AccessEventRecord $event,
        int $version,
        string $decidedAt,
    ): AccessEventOperationalDecisionRecord {
        return AccessEventOperationalDecisionRecord::query()
            ->create([
                'access_event_id' => $event->id,
                'tenant_id' => $event->tenant_id,
                'organization_id' => $event->organization_id,
                'visitor_id' => null,
                'visit_id' => null,
                'version' => $version,
                'decision' => AccessEventOperationalDecision::ManualReview,
                'reason_code' => 'relation_test_'.$version,
                'reason_message' => 'Decisão para teste de relação.',
                'automatic_execution_enabled' => false,
                'decided_at' => new DateTimeImmutable(
                    $decidedAt
                ),
            ]);
    }

    private function createExecution(
        AccessEventRecord $event,
        AccessEventOperationalDecisionRecord $decision,
        string $attemptedAt,
    ): AccessEventOperationalExecutionRecord {
        return AccessEventOperationalExecutionRecord::query()
            ->create([
                'operational_decision_id' => $decision->id,
                'access_event_id' => $event->id,
                'tenant_id' => $event->tenant_id,
                'organization_id' => $event->organization_id,
                'visitor_id' => null,
                'visit_id' => null,
                'operator_user_id' => null,
                'attempt_number' => 1,
                'source' => AccessEventOperationalExecutionSource::Automatic,
                'status' => AccessEventOperationalExecutionStatus::Skipped,
                'reason_code' => 'relation_test_execution',
                'reason_message' => 'Tentativa para teste de relação.',
                'automatic_execution_allowed' => false,
                'visit_status_before' => null,
                'visit_status_after' => null,
                'attempted_at' => new DateTimeImmutable(
                    $attemptedAt
                ),
                'completed_at' => new DateTimeImmutable(
                    $attemptedAt
                ),
            ]);
    }
}
