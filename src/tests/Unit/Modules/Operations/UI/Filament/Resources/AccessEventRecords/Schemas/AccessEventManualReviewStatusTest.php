<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Schemas;

use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewConsumptionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Schemas\AccessEventManualReviewStatus;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccessEventManualReviewStatusTest extends TestCase
{
    public function test_it_hides_the_summary_for_a_non_manual_decision(): void
    {
        $event = $this->eventWithDecision(
            AccessEventOperationalDecision::NoAction
        );

        $summary =
            AccessEventManualReviewStatus::summary(
                $event
            );

        $this->assertFalse(
            $summary['visible']
        );
    }

    public function test_it_presents_a_pending_correction(): void
    {
        $event = $this->eventWithReview(
            AccessEventManualReviewDisposition::PendingCorrection
        );

        $summary =
            AccessEventManualReviewStatus::summary(
                $event
            );

        $this->assertTrue(
            $summary['visible']
        );

        $this->assertSame(
            'Aguardando correção',
            $summary['analysis_status']
        );

        $this->assertSame(
            'Não concedida',
            $summary['release_status']
        );

        $this->assertFalse(
            $summary['release_consumed']
        );

        $this->assertSame(
            'Corrigir a pendência e registrar uma nova análise manual.',
            $summary['next_action']
        );
    }

    public function test_it_presents_an_available_single_use_release(): void
    {
        $event = $this->eventWithReview(
            AccessEventManualReviewDisposition::ReadyForReprocessing
        );

        $summary =
            AccessEventManualReviewStatus::summary(
                $event
            );

        $this->assertSame(
            'Pronto para reprocessamento',
            $summary['analysis_status']
        );

        $this->assertSame(
            'Disponível para uso único',
            $summary['release_status']
        );

        $this->assertFalse(
            $summary['release_consumed']
        );

        $this->assertSame(
            'Usar “Reprocessar fluxo” para recalcular a decisão operacional.',
            $summary['next_action']
        );
    }

    public function test_it_presents_a_consumed_release_without_internal_ids(): void
    {
        $event = $this->eventWithReview(
            disposition: AccessEventManualReviewDisposition::ReadyForReprocessing,

            consumed: true,
        );

        $summary =
            AccessEventManualReviewStatus::summary(
                $event
            );

        $this->assertSame(
            'Consumida',
            $summary['release_status']
        );

        $this->assertTrue(
            $summary['release_consumed']
        );

        $this->assertSame(
            'OPERADOR DO CONSUMO',
            $summary['consumed_by']
        );

        $this->assertInstanceOf(
            Carbon::class,
            $summary['consumed_at']
        );

        $this->assertSame(
            'Registrar uma nova análise manual para liberar outra tentativa de reprocessamento.',
            $summary['next_action']
        );

        $serialized = json_encode(
            $summary,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ) ?: '';

        foreach ([
            'internal-review-id',
            'internal-consumption-id',
            'internal-decision-id',
        ] as $internalId) {
            $this->assertStringNotContainsString(
                $internalId,
                $serialized
            );
        }
    }

    public function test_it_presents_a_resolution_without_operation(): void
    {
        $event = $this->eventWithReview(
            AccessEventManualReviewDisposition::ResolvedWithoutOperation
        );

        $summary =
            AccessEventManualReviewStatus::summary(
                $event
            );

        $this->assertSame(
            'Resolvido sem operação',
            $summary['analysis_status']
        );

        $this->assertSame(
            'Não aplicável',
            $summary['release_status']
        );

        $this->assertSame(
            'Nenhuma ação operacional adicional; o evento foi encerrado sem registrar entrada ou saída.',
            $summary['next_action']
        );
    }

    public function test_it_identifies_a_review_from_an_old_decision(): void
    {
        $event = $this->eventWithReview(
            disposition: AccessEventManualReviewDisposition::ReadyForReprocessing,

            consumed: false,
            reviewVersion: 1,
            decisionVersion: 2,
        );

        $summary =
            AccessEventManualReviewStatus::summary(
                $event
            );

        $this->assertSame(
            'Análise desatualizada',
            $summary['analysis_status']
        );

        $this->assertSame(
            'Não concedida',
            $summary['release_status']
        );

        $this->assertSame(
            'Registrar uma nova análise manual para a decisão operacional atual.',
            $summary['next_action']
        );
    }

    private function eventWithDecision(
        AccessEventOperationalDecision $decisionState
    ): AccessEventRecord {
        $event = new AccessEventRecord;

        $event->forceFill([
            'id' => 'internal-event-id',
        ]);

        $decision =
            new AccessEventOperationalDecisionRecord;

        $decision->forceFill([
            'id' => 'internal-decision-id',

            'access_event_id' => 'internal-event-id',

            'version' => 1,
            'decision' => $decisionState,
        ]);

        $event->setRelation(
            'latestOperationalDecision',
            $decision
        );

        $event->setRelation(
            'latestManualReview',
            null
        );

        return $event;
    }

    private function eventWithReview(
        AccessEventManualReviewDisposition $disposition,
        bool $consumed = false,
        int $reviewVersion = 1,
        int $decisionVersion = 1,
    ): AccessEventRecord {
        $event = new AccessEventRecord;

        $event->forceFill([
            'id' => 'internal-event-id',
        ]);

        $decision =
            new AccessEventOperationalDecisionRecord;

        $decision->forceFill([
            'id' => 'internal-decision-id',

            'access_event_id' => 'internal-event-id',

            'version' => $decisionVersion,

            'decision' => AccessEventOperationalDecision::ManualReview,
        ]);

        $review =
            new AccessEventManualReviewRecord;

        $review->forceFill([
            'id' => 'internal-review-id',

            'access_event_id' => 'internal-event-id',

            'operational_decision_id' => 'internal-decision-id',

            'decision_version' => $reviewVersion,

            'operator_name' => 'OPERADOR DA ANÁLISE',

            'disposition' => $disposition,

            'notes' => 'Observação sintética da análise.',

            'reviewed_at' => Carbon::parse(
                '2026-07-17 11:27:25'
            ),
        ]);

        if ($consumed) {
            $consumption =
                new AccessEventManualReviewConsumptionRecord;

            $consumption->forceFill([
                'id' => 'internal-consumption-id',

                'manual_review_id' => 'internal-review-id',

                'operator_name' => 'OPERADOR DO CONSUMO',

                'consumed_at' => Carbon::parse(
                    '2026-07-17 13:50:25'
                ),
            ]);

            $review->setRelation(
                'reprocessConsumption',
                $consumption
            );
        } else {
            $review->setRelation(
                'reprocessConsumption',
                null
            );
        }

        $event->setRelation(
            'latestOperationalDecision',
            $decision
        );

        $event->setRelation(
            'latestManualReview',
            $review
        );

        return $event;
    }
}
