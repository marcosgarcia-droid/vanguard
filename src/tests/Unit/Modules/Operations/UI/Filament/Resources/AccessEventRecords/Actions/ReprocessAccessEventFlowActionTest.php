<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions;

use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions\ReprocessAccessEventFlowAction;
use Filament\Actions\Action;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class ReprocessAccessEventFlowActionTest extends TestCase
{
    public function test_it_creates_the_reprocess_action(): void
    {
        $action =
            ReprocessAccessEventFlowAction::make();

        $this->assertInstanceOf(
            Action::class,
            $action
        );

        $this->assertSame(
            'reprocessAccessEventFlow',
            $action->getName()
        );
    }

    public function test_it_allows_regular_operational_reprocessing(): void
    {
        $event = new AccessEventRecord;

        $decision =
            new AccessEventOperationalDecisionRecord;

        $decision->forceFill([
            'id' => (string) Str::uuid(),
            'version' => 1,
            'decision' => AccessEventOperationalDecision::CheckInCandidate,
        ]);

        $event->setRelation(
            'latestOperationalDecision',
            $decision
        );

        $this->assertTrue(
            ReprocessAccessEventFlowAction::isEligibleRecord(
                $event
            )
        );
    }

    public function test_it_requires_a_current_ready_manual_review(): void
    {
        $decisionId = (string) Str::uuid();

        $decision =
            new AccessEventOperationalDecisionRecord;

        $decision->forceFill([
            'id' => $decisionId,
            'version' => 3,
            'decision' => AccessEventOperationalDecision::ManualReview,
        ]);

        $cases = [
            [
                'disposition' => null,
                'expected' => false,
            ],
            [
                'disposition' => AccessEventManualReviewDisposition::PendingCorrection,
                'expected' => false,
            ],
            [
                'disposition' => AccessEventManualReviewDisposition::ReadyForReprocessing,
                'expected' => true,
            ],
            [
                'disposition' => AccessEventManualReviewDisposition::ResolvedWithoutOperation,
                'expected' => false,
            ],
        ];

        foreach ($cases as $case) {
            $event = new AccessEventRecord;

            $event->setRelation(
                'latestOperationalDecision',
                $decision
            );

            $disposition =
                $case['disposition'];

            if (
                $disposition
                instanceof AccessEventManualReviewDisposition
            ) {
                $review =
                    new AccessEventManualReviewRecord;

                $review->forceFill([
                    'operational_decision_id' => $decisionId,

                    'decision_version' => 3,
                    'disposition' => $disposition,
                ]);

                $event->setRelation(
                    'latestManualReview',
                    $review
                );
            } else {
                $event->setRelation(
                    'latestManualReview',
                    null
                );
            }

            $this->assertSame(
                $case['expected'],
                ReprocessAccessEventFlowAction::isEligibleRecord(
                    $event
                )
            );
        }
    }

    public function test_it_rejects_a_ready_review_from_an_old_decision(): void
    {
        $decision =
            new AccessEventOperationalDecisionRecord;

        $decision->forceFill([
            'id' => (string) Str::uuid(),
            'version' => 2,
            'decision' => AccessEventOperationalDecision::ManualReview,
        ]);

        $review =
            new AccessEventManualReviewRecord;

        $review->forceFill([
            'operational_decision_id' => (string) Str::uuid(),

            'decision_version' => 1,

            'disposition' => AccessEventManualReviewDisposition::ReadyForReprocessing,
        ]);

        $event = new AccessEventRecord;

        $event->setRelation(
            'latestOperationalDecision',
            $decision
        );

        $event->setRelation(
            'latestManualReview',
            $review
        );

        $this->assertFalse(
            ReprocessAccessEventFlowAction::isEligibleRecord(
                $event
            )
        );
    }

    public function test_it_authorizes_calls_the_controlled_wrapper_and_audits(): void
    {
        $source = $this->source();

        foreach ([
            'Gate::authorize(',
            "'reprocessFlow'",
            'ReprocessAccessEventFlowUseCase::class',
            'ReprocessAccessEventFlowCommand(',
            'operatorUserId: (int) $user->id',
            'isEligibleRecord(',
            "->event(\n                'access_event_flow_reprocessed'",
            "activity(\n            'access_control'",
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        foreach ([
            'ContinueAccessEventFlowUseCase::class',
            'ContinueAccessEventFlowCommand(',
            'Http::',
            'raw_payload',
            'openDoor',
            'unlock',
            'relay',
            'setConfig',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    private function source(): string
    {
        $filename = (
            new ReflectionClass(
                ReprocessAccessEventFlowAction::class
            )
        )->getFileName();

        $this->assertIsString($filename);

        $source = file_get_contents($filename);

        $this->assertIsString($source);

        return $source;
    }
}
