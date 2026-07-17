<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewConsumptionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AccessEventManualReviewConsumptionRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_preserves_a_single_consumption_per_review(): void
    {
        $context = $this->createContext();

        $consumption =
            $this->createConsumption($context);

        $this->assertTrue(
            $consumption->event->is(
                $context['event']
            )
        );

        $this->assertTrue(
            $consumption->review->is(
                $context['review']
            )
        );

        $this->assertTrue(
            $consumption->decision->is(
                $context['decision']
            )
        );

        $this->assertTrue(
            $consumption->operator->is(
                $context['operator']
            )
        );

        $this->assertSame(
            AccessEventManualReviewDisposition::ReadyForReprocessing,
            $consumption->disposition
        );

        $this->expectException(
            QueryException::class
        );

        $this->createConsumption(
            $context
        );
    }

    public function test_it_cannot_be_updated(): void
    {
        $consumption = $this->createConsumption(
            $this->createContext()
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Os consumos de análises manuais são imutáveis.'
        );

        $consumption
            ->forceFill([
                'operator_name' => 'ALTERADO',
            ])
            ->save();
    }

    public function test_it_cannot_be_deleted(): void
    {
        $consumption = $this->createConsumption(
            $this->createContext()
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Os consumos de análises manuais não podem ser excluídos.'
        );

        $consumption->delete();
    }

    /**
     * @return array{
     *     event: AccessEventRecord,
     *     review: AccessEventManualReviewRecord,
     *     decision: AccessEventOperationalDecisionRecord,
     *     operator: User
     * }
     */
    private function createContext(): array
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO CONSUMO DE REVISÃO',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',

                'legal_name' => 'UNIDADE CONSUMO DE REVISÃO LTDA',

                'display_name' => 'UNIDADE CONSUMO DE REVISÃO',

                'unit_code' => 'CRV-01',
            ]);

        $device = AccessDeviceRecord::query()
            ->create([
                'tenant_id' => $tenant->id,

                'organization_id' => $organization->id,

                'code' => 'FAC-CRV-01',

                'name' => 'LEITOR CONSUMO DE REVISÃO',

                'provider' => 'simulator',

                'direction' => AccessDeviceDirection::Entry,

                'status' => AccessDeviceStatus::Active,
            ]);

        $event = AccessEventRecord::query()
            ->create([
                'access_device_id' => $device->id,
                'tenant_id' => $tenant->id,

                'organization_id' => $organization->id,

                'external_event_id' => 'review-consumption-'.Str::uuid(),

                'event_type' => 'face_recognition',

                'direction' => AccessEventDirection::Entry,

                'occurred_at' => now(),

                'status' => AccessEventStatus::Processed,

                'received_at' => now(),
                'processed_at' => now(),
                'processing_attempts' => 1,
            ]);

        $decision =
            AccessEventOperationalDecisionRecord::query()
                ->create([
                    'access_event_id' => $event->id,
                    'tenant_id' => $tenant->id,

                    'organization_id' => $organization->id,

                    'visitor_id' => null,
                    'visit_id' => null,
                    'version' => 1,

                    'decision' => AccessEventOperationalDecision::ManualReview,

                    'reason_code' => 'consumption_test',

                    'reason_message' => 'Decisão sintética para consumo.',

                    'automatic_execution_enabled' => false,

                    'decided_at' => now(),
                ]);

        $operator = User::factory()->create([
            'name' => 'OPERADOR CONSUMO DE REVISÃO',
        ]);

        $review =
            AccessEventManualReviewRecord::query()
                ->create([
                    'access_event_id' => $event->id,

                    'operational_decision_id' => $decision->id,

                    'tenant_id' => $tenant->id,

                    'organization_id' => $organization->id,

                    'visitor_id' => null,
                    'visit_id' => null,

                    'operator_user_id' => $operator->id,

                    'idempotency_key' => (string) Str::uuid(),

                    'operator_name' => $operator->name,

                    'decision_version' => 1,

                    'decision_reason_code' => $decision->reason_code,

                    'decision_reason_message' => $decision->reason_message,

                    'disposition' => AccessEventManualReviewDisposition::ReadyForReprocessing,

                    'notes' => 'Análise sintética pronta para reprocessamento.',

                    'reviewed_at' => now(),
                ]);

        return [
            'event' => $event,
            'review' => $review,
            'decision' => $decision,
            'operator' => $operator,
        ];
    }

    /**
     * @param array{
     *     event: AccessEventRecord,
     *     review: AccessEventManualReviewRecord,
     *     decision: AccessEventOperationalDecisionRecord,
     *     operator: User
     * } $context
     */
    private function createConsumption(
        array $context
    ): AccessEventManualReviewConsumptionRecord {
        return AccessEventManualReviewConsumptionRecord::query()
            ->create([
                'access_event_id' => $context['event']->id,

                'manual_review_id' => $context['review']->id,

                'operational_decision_id' => $context['decision']->id,

                'tenant_id' => $context['event']->tenant_id,

                'organization_id' => $context['event']->organization_id,

                'visitor_id' => null,
                'visit_id' => null,

                'operator_user_id' => $context['operator']->id,

                'idempotency_key' => (string) Str::uuid(),

                'operator_name' => $context['operator']->name,

                'decision_version' => $context['decision']->version,

                'disposition' => AccessEventManualReviewDisposition::ReadyForReprocessing,

                'consumed_at' => now(),
            ]);
    }
}
