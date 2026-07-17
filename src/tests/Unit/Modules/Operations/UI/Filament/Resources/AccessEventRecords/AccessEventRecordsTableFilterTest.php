<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\AccessEventRecords;

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
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalExecutionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Tables\AccessEventRecordsTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class AccessEventRecordsTableFilterTest extends TestCase
{
    use RefreshDatabase;

    private TenantRecord $tenant;

    private OrganizationRecord $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO FILTROS SINTÉTICOS',
            'status' => 'active',
        ]);

        $this->organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE FILTROS SINTÉTICOS LTDA',
                'display_name' => 'UNIDADE FILTROS SINTÉTICOS',
                'unit_code' => 'FIL-01',
            ]);
    }

    public function test_it_declares_safe_operational_search_and_filters(): void
    {
        $reflection = new ReflectionClass(
            AccessEventRecordsTable::class
        );

        $source = file_get_contents(
            (string) $reflection->getFileName()
        );

        $this->assertIsString($source);

        foreach ([
            'self::applyOperationalSearch(',
            "TextColumn::make(
                    'operational_status'
                )",
            'AccessEventOperationalStatus::summary(',
            "Filter::make('occurred_at_period')",
            "SelectFilter::make(\n                    'latest_operational_decision'",
            "SelectFilter::make(\n                    'latest_operational_execution_status'",
            "TernaryFilter::make('visitor_id')",
            "TernaryFilter::make('visit_id')",
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        foreach ([
            'DeleteAction::make()',
            'EditAction::make()',
            'BulkAction',
            'raw_payload',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_it_searches_by_person_device_and_external_identifiers(): void
    {
        $alpha = $this->createEvent(
            suffix: 'ALPHA',
            visitorName: 'ANA ALFA',
            occurredAt: '2026-07-15 08:00:00',
        );

        $beta = $this->createEvent(
            suffix: 'BETA',
            visitorName: 'MARIA BETA',
            occurredAt: '2026-07-16 09:00:00',
        );

        foreach ([
            'MARIA BETA',
            'FAC-BETA',
            'LEITOR BETA',
            'external-event-BETA',
            'external-person-BETA',
        ] as $search) {
            $ids = AccessEventRecordsTable::applyOperationalSearch(
                AccessEventRecord::query(),
                $search
            )
                ->pluck('id')
                ->all();

            $this->assertSame(
                [$beta->id],
                $ids,
                "Pesquisa não localizou corretamente: {$search}"
            );
        }

        $this->assertNotSame(
            $alpha->id,
            $beta->id
        );
    }

    public function test_it_filters_an_inclusive_event_date_period(): void
    {
        $before = $this->createEvent(
            suffix: 'BEFORE',
            visitorName: 'PESSOA ANTERIOR',
            occurredAt: '2026-07-15 23:59:59',
        );

        $inside = $this->createEvent(
            suffix: 'INSIDE',
            visitorName: 'PESSOA DO PERÍODO',
            occurredAt: '2026-07-16 23:59:59',
        );

        $after = $this->createEvent(
            suffix: 'AFTER',
            visitorName: 'PESSOA POSTERIOR',
            occurredAt: '2026-07-17 00:00:00',
        );

        $ids = AccessEventRecordsTable::applyOccurredAtPeriod(
            AccessEventRecord::query(),
            [
                'from' => '2026-07-16',
                'until' => '2026-07-16',
            ]
        )
            ->pluck('id')
            ->all();

        $this->assertSame(
            [$inside->id],
            $ids
        );

        $this->assertNotContains(
            $before->id,
            $ids
        );

        $this->assertNotContains(
            $after->id,
            $ids
        );
    }

    public function test_it_filters_only_the_latest_operational_decision(): void
    {
        $historicalManualReview =
            $this->createEvent(
                suffix: 'HISTORICAL-DECISION',
                visitorName: 'DECISÃO ALTERADA',
                occurredAt: '2026-07-16 08:00:00',
            );

        $currentManualReview =
            $this->createEvent(
                suffix: 'CURRENT-DECISION',
                visitorName: 'DECISÃO ATUAL',
                occurredAt: '2026-07-16 09:00:00',
            );

        $this->createDecision(
            $historicalManualReview,
            1,
            AccessEventOperationalDecision::ManualReview
        );

        $this->createDecision(
            $historicalManualReview,
            2,
            AccessEventOperationalDecision::CheckInCandidate
        );

        $this->createDecision(
            $currentManualReview,
            1,
            AccessEventOperationalDecision::ManualReview
        );

        $manualReviewIds =
            AccessEventRecordsTable::applyLatestDecisionFilter(
                AccessEventRecord::query(),
                AccessEventOperationalDecision::ManualReview->value
            )
                ->pluck('id')
                ->all();

        $checkInIds =
            AccessEventRecordsTable::applyLatestDecisionFilter(
                AccessEventRecord::query(),
                AccessEventOperationalDecision::CheckInCandidate->value
            )
                ->pluck('id')
                ->all();

        $this->assertSame(
            [$currentManualReview->id],
            $manualReviewIds
        );

        $this->assertSame(
            [$historicalManualReview->id],
            $checkInIds
        );
    }

    public function test_it_filters_only_the_latest_execution_attempt(): void
    {
        $historicalBlocked =
            $this->createEvent(
                suffix: 'HISTORICAL-EXECUTION',
                visitorName: 'TENTATIVA ALTERADA',
                occurredAt: '2026-07-16 10:00:00',
            );

        $currentBlocked =
            $this->createEvent(
                suffix: 'CURRENT-EXECUTION',
                visitorName: 'TENTATIVA ATUAL',
                occurredAt: '2026-07-16 11:00:00',
            );

        $historicalDecision =
            $this->createDecision(
                $historicalBlocked,
                1,
                AccessEventOperationalDecision::CheckInCandidate
            );

        $currentDecision =
            $this->createDecision(
                $currentBlocked,
                1,
                AccessEventOperationalDecision::CheckInCandidate
            );

        $this->createExecution(
            event: $historicalBlocked,
            decision: $historicalDecision,
            attemptNumber: 1,
            status: AccessEventOperationalExecutionStatus::Blocked,
            attemptedAt: '2026-07-16 10:01:00',
        );

        $this->createExecution(
            event: $historicalBlocked,
            decision: $historicalDecision,
            attemptNumber: 2,
            status: AccessEventOperationalExecutionStatus::Executed,
            attemptedAt: '2026-07-16 10:02:00',
        );

        $this->createExecution(
            event: $currentBlocked,
            decision: $currentDecision,
            attemptNumber: 1,
            status: AccessEventOperationalExecutionStatus::Blocked,
            attemptedAt: '2026-07-16 11:01:00',
        );

        $blockedIds =
            AccessEventRecordsTable::applyLatestExecutionStatusFilter(
                AccessEventRecord::query(),
                AccessEventOperationalExecutionStatus::Blocked->value
            )
                ->pluck('id')
                ->all();

        $executedIds =
            AccessEventRecordsTable::applyLatestExecutionStatusFilter(
                AccessEventRecord::query(),
                AccessEventOperationalExecutionStatus::Executed->value
            )
                ->pluck('id')
                ->all();

        $this->assertSame(
            [$currentBlocked->id],
            $blockedIds
        );

        $this->assertSame(
            [$historicalBlocked->id],
            $executedIds
        );
    }

    private function createEvent(
        string $suffix,
        string $visitorName,
        string $occurredAt,
    ): AccessEventRecord {
        $device = AccessDeviceRecord::query()->create([
            'tenant_id' => $this->tenant->id,
            'organization_id' => $this->organization->id,
            'code' => "FAC-{$suffix}",
            'name' => "LEITOR {$suffix}",
            'provider' => 'simulator',
            'direction' => AccessDeviceDirection::Entry,
            'status' => AccessDeviceStatus::Active,
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $this->tenant->id,
            'organization_id' => $this->organization->id,
            'full_name' => $visitorName,
            'status' => VisitorStatus::Active,
        ]);

        return AccessEventRecord::query()->create([
            'access_device_id' => $device->id,
            'tenant_id' => $this->tenant->id,
            'organization_id' => $this->organization->id,
            'visitor_id' => $visitor->id,
            'external_event_id' => "external-event-{$suffix}",
            'external_person_id' => "external-person-{$suffix}",
            'event_type' => 'face_recognition',
            'direction' => AccessEventDirection::Entry,
            'occurred_at' => $occurredAt,
            'status' => AccessEventStatus::Processed,
            'received_at' => $occurredAt,
            'processed_at' => $occurredAt,
            'processing_attempts' => 1,
        ]);
    }

    private function createDecision(
        AccessEventRecord $event,
        int $version,
        AccessEventOperationalDecision $decision,
    ): AccessEventOperationalDecisionRecord {
        return AccessEventOperationalDecisionRecord::query()
            ->create([
                'access_event_id' => $event->id,
                'tenant_id' => $event->tenant_id,
                'organization_id' => $event->organization_id,
                'visitor_id' => $event->visitor_id,
                'visit_id' => null,
                'version' => $version,
                'decision' => $decision,
                'reason_code' => "filter_decision_{$version}",
                'reason_message' => 'Decisão sintética para teste.',
                'automatic_execution_enabled' => false,
                'decided_at' => $event->occurred_at
                    ->copy()
                    ->addMinutes($version),
            ]);
    }

    private function createExecution(
        AccessEventRecord $event,
        AccessEventOperationalDecisionRecord $decision,
        int $attemptNumber,
        AccessEventOperationalExecutionStatus $status,
        string $attemptedAt,
    ): AccessEventOperationalExecutionRecord {
        return AccessEventOperationalExecutionRecord::query()
            ->create([
                'operational_decision_id' => $decision->id,
                'access_event_id' => $event->id,
                'tenant_id' => $event->tenant_id,
                'organization_id' => $event->organization_id,
                'visitor_id' => $event->visitor_id,
                'visit_id' => null,
                'operator_user_id' => null,
                'attempt_number' => $attemptNumber,
                'source' => AccessEventOperationalExecutionSource::Automatic,
                'status' => $status,
                'reason_code' => "filter_execution_{$attemptNumber}",
                'reason_message' => 'Tentativa sintética para teste.',
                'automatic_execution_allowed' => false,
                'visit_status_before' => null,
                'visit_status_after' => null,
                'attempted_at' => $attemptedAt,
                'completed_at' => $attemptedAt,
            ]);
    }
}
