<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Tables;

use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewConsumptionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalExecutionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Tables\AccessEventOperationalStatus;
use ReflectionClass;
use Tests\TestCase;

class AccessEventOperationalStatusTest extends TestCase
{
    public function test_it_prioritizes_the_current_operational_state(): void
    {
        $cases = [
            [
                $this->event(
                    status: AccessEventStatus::Failed
                ),
                'processing_failed',
                'Falha no processamento',
                'danger',
            ],
            [
                $this->event(
                    status: AccessEventStatus::PendingAssociation
                ),
                'pending_association',
                'Aguardando associação',
                'warning',
            ],
            [
                $this->event(
                    status: AccessEventStatus::Received
                ),
                'awaiting_processing',
                'Aguardando processamento',
                'info',
            ],
            [
                $this->event(
                    decision: AccessEventOperationalDecision::ManualReview,
                    execution: AccessEventOperationalExecutionStatus::Skipped,
                ),
                'awaiting_manual_review',
                'Aguardando análise manual',
                'warning',
            ],
            [
                $this->event(
                    decision: AccessEventOperationalDecision::ManualReview,
                    review: AccessEventManualReviewDisposition::PendingCorrection,
                ),
                'pending_correction',
                'Aguardando correção',
                'warning',
            ],
            [
                $this->event(
                    decision: AccessEventOperationalDecision::ManualReview,
                    review: AccessEventManualReviewDisposition::ReadyForReprocessing,
                ),
                'ready_for_reprocessing',
                'Pronto para reprocessamento',
                'success',
            ],
            [
                $this->event(
                    decision: AccessEventOperationalDecision::ManualReview,
                    review: AccessEventManualReviewDisposition::ReadyForReprocessing,
                    consumed: true,
                ),
                'manual_review_release_consumed',
                'Liberação consumida',
                'warning',
            ],
            [
                $this->event(
                    decision: AccessEventOperationalDecision::ManualReview,
                    review: AccessEventManualReviewDisposition::ResolvedWithoutOperation,
                ),
                'resolved_without_operation',
                'Revisão encerrada sem operação',
                'gray',
            ],
            [
                $this->event(
                    decision: AccessEventOperationalDecision::ManualReview,
                    review: AccessEventManualReviewDisposition::ReadyForReprocessing,
                    staleReview: true,
                ),
                'outdated_manual_review',
                'Análise manual desatualizada',
                'danger',
            ],
            [
                $this->event(
                    decision: AccessEventOperationalDecision::CheckInCandidate,
                    execution: AccessEventOperationalExecutionStatus::Blocked,
                ),
                'execution_blocked',
                'Tentativa bloqueada',
                'warning',
            ],
            [
                $this->event(
                    decision: AccessEventOperationalDecision::CheckOutCandidate,
                    execution: AccessEventOperationalExecutionStatus::Executed,
                ),
                'execution_completed',
                'Operação executada',
                'success',
            ],
            [
                $this->event(
                    decision: AccessEventOperationalDecision::CheckInCandidate,
                ),
                'check_in_candidate',
                'Candidato a entrada',
                'success',
            ],
            [
                $this->event(
                    decision: AccessEventOperationalDecision::CheckOutCandidate,
                ),
                'check_out_candidate',
                'Candidato a saída',
                'warning',
            ],
            [
                $this->event(
                    decision: AccessEventOperationalDecision::NoAction,
                    execution: AccessEventOperationalExecutionStatus::Skipped,
                ),
                'no_action',
                'Sem ação operacional',
                'gray',
            ],
            [
                $this->event(),
                'awaiting_decision',
                'Aguardando decisão operacional',
                'info',
            ],
        ];

        foreach (
            $cases as [
                $event,
                $expectedCode,
                $expectedLabel,
                $expectedColor,
            ]
        ) {
            $summary =
                AccessEventOperationalStatus::summary(
                    $event
                );

            $this->assertSame(
                $expectedCode,
                $summary['code']
            );

            $this->assertSame(
                $expectedLabel,
                $summary['label']
            );

            $this->assertSame(
                $expectedColor,
                $summary['color']
            );

            $this->assertNotSame(
                '',
                trim($summary['description'])
            );
        }
    }

    public function test_it_uses_only_preloaded_relations(): void
    {
        $reflection = new ReflectionClass(
            AccessEventOperationalStatus::class
        );

        $source = file_get_contents(
            (string) $reflection->getFileName()
        );

        $this->assertIsString($source);

        foreach ([
            "relationLoaded(\n                'latestOperationalDecision'",
            "relationLoaded(\n                'latestOperationalExecution'",
            "relationLoaded(\n                'latestManualReview'",
            "relationLoaded(\n                'reprocessConsumption'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach ([
            '->latestOperationalDecision()',
            '->latestOperationalExecution()',
            '->latestManualReview()',
            '->reprocessConsumption()',
            'DB::',
            'Http::',
            'dispatch(',
            '->save(',
            '->update(',
            '->delete(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    private function event(
        AccessEventStatus $status = AccessEventStatus::Processed,
        ?AccessEventOperationalDecision $decision = null,
        ?AccessEventOperationalExecutionStatus $execution = null,
        ?AccessEventManualReviewDisposition $review = null,
        bool $consumed = false,
        bool $staleReview = false,
    ): AccessEventRecord {
        $event = new AccessEventRecord;

        $event->forceFill([
            'id' => 'event-current',
            'status' => $status,
        ]);

        if (
            $decision
            instanceof AccessEventOperationalDecision
        ) {
            $decisionRecord =
                new AccessEventOperationalDecisionRecord;

            $decisionRecord->forceFill([
                'id' => 'decision-current',
                'access_event_id' => 'event-current',
                'version' => 2,
                'decision' => $decision,
            ]);

            $event->setRelation(
                'latestOperationalDecision',
                $decisionRecord
            );
        } else {
            $event->setRelation(
                'latestOperationalDecision',
                null
            );
        }

        if (
            $execution
            instanceof AccessEventOperationalExecutionStatus
        ) {
            $executionRecord =
                new AccessEventOperationalExecutionRecord;

            $executionRecord->forceFill([
                'id' => 'execution-current',
                'access_event_id' => 'event-current',
                'status' => $execution,
            ]);

            $event->setRelation(
                'latestOperationalExecution',
                $executionRecord
            );
        } else {
            $event->setRelation(
                'latestOperationalExecution',
                null
            );
        }

        if (
            $review
            instanceof AccessEventManualReviewDisposition
        ) {
            $reviewRecord =
                new AccessEventManualReviewRecord;

            $reviewRecord->forceFill([
                'id' => 'review-current',
                'access_event_id' => 'event-current',

                'operational_decision_id' => $staleReview
                        ? 'decision-old'
                        : 'decision-current',

                'decision_version' => $staleReview
                        ? 1
                        : 2,

                'disposition' => $review,
            ]);

            if ($consumed) {
                $consumption =
                    new AccessEventManualReviewConsumptionRecord;

                $consumption->forceFill([
                    'id' => 'consumption-current',

                    'manual_review_id' => 'review-current',
                ]);

                $reviewRecord->setRelation(
                    'reprocessConsumption',
                    $consumption
                );
            } else {
                $reviewRecord->setRelation(
                    'reprocessConsumption',
                    null
                );
            }

            $event->setRelation(
                'latestManualReview',
                $reviewRecord
            );
        } else {
            $event->setRelation(
                'latestManualReview',
                null
            );
        }

        return $event;
    }
}
