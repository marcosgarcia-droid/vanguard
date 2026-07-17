<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions;

use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions\RecordAccessEventManualReviewAction;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Tables\AccessEventRecordsTable;
use Filament\Actions\Action;
use ReflectionClass;
use Tests\TestCase;

class RecordAccessEventManualReviewActionTest extends TestCase
{
    public function test_it_creates_the_manual_review_action(): void
    {
        $action =
            RecordAccessEventManualReviewAction::make();

        $this->assertInstanceOf(
            Action::class,
            $action
        );

        $this->assertSame(
            'recordAccessEventManualReview',
            $action->getName()
        );
    }

    public function test_it_exposes_the_three_review_dispositions(): void
    {
        $this->assertSame(
            [
                AccessEventManualReviewDisposition::PendingCorrection
                    ->value => 'Aguardando correção',

                AccessEventManualReviewDisposition::ReadyForReprocessing
                    ->value => 'Pronto para reprocessamento',

                AccessEventManualReviewDisposition::ResolvedWithoutOperation
                    ->value => 'Resolvido sem operação',
            ],
            RecordAccessEventManualReviewAction::dispositionOptions()
        );
    }

    public function test_it_accepts_only_events_with_a_current_manual_review_decision(): void
    {
        $manualReviewEvent =
            new AccessEventRecord;

        $manualReviewDecision =
            new AccessEventOperationalDecisionRecord;

        $manualReviewDecision->forceFill([
            'decision' => AccessEventOperationalDecision::ManualReview,
        ]);

        $manualReviewEvent->setRelation(
            'latestOperationalDecision',
            $manualReviewDecision
        );

        $candidateEvent =
            new AccessEventRecord;

        $candidateDecision =
            new AccessEventOperationalDecisionRecord;

        $candidateDecision->forceFill([
            'decision' => AccessEventOperationalDecision::CheckInCandidate,
        ]);

        $candidateEvent->setRelation(
            'latestOperationalDecision',
            $candidateDecision
        );

        $withoutDecision =
            new AccessEventRecord;

        $withoutDecision->setRelation(
            'latestOperationalDecision',
            null
        );

        $this->assertTrue(
            RecordAccessEventManualReviewAction::isEligibleRecord(
                $manualReviewEvent
            )
        );

        $this->assertFalse(
            RecordAccessEventManualReviewAction::isEligibleRecord(
                $candidateEvent
            )
        );

        $this->assertFalse(
            RecordAccessEventManualReviewAction::isEligibleRecord(
                $withoutDecision
            )
        );
    }

    public function test_it_hides_the_action_after_resolution_without_operation(): void
    {
        $event = new AccessEventRecord;

        $decision =
            new AccessEventOperationalDecisionRecord;

        $decision->forceFill([
            'decision' => AccessEventOperationalDecision::ManualReview,
        ]);

        $review =
            new AccessEventManualReviewRecord;

        $review->forceFill([
            'disposition' => AccessEventManualReviewDisposition::ResolvedWithoutOperation,
        ]);

        $event->setRelation(
            'latestOperationalDecision',
            $decision
        );

        $event->setRelation(
            'latestManualReview',
            $review
        );

        $this->assertFalse(
            RecordAccessEventManualReviewAction::isEligibleRecord(
                $event
            )
        );
    }

    public function test_it_keeps_the_action_available_for_an_open_review(): void
    {
        $event = new AccessEventRecord;

        $decision =
            new AccessEventOperationalDecisionRecord;

        $decision->forceFill([
            'decision' => AccessEventOperationalDecision::ManualReview,
        ]);

        $review =
            new AccessEventManualReviewRecord;

        $review->forceFill([
            'disposition' => AccessEventManualReviewDisposition::PendingCorrection,
        ]);

        $event->setRelation(
            'latestOperationalDecision',
            $decision
        );

        $event->setRelation(
            'latestManualReview',
            $review
        );

        $this->assertTrue(
            RecordAccessEventManualReviewAction::isEligibleRecord(
                $event
            )
        );
    }

    public function test_it_declares_the_controlled_modal_authorization_and_audit(): void
    {
        $source = $this->sourceOf(
            RecordAccessEventManualReviewAction::class
        );

        foreach ([
            "Select::make('disposition')",
            "Textarea::make('notes')",
            "Hidden::make('idempotency_key')",
            '->minLength(10)',
            '->maxLength(2000)',
            "Gate::authorize(\n                        'resolveManualReview'",
            'RecordAccessEventManualReviewUseCase::class',
            'RecordAccessEventManualReviewCommand(',
            'operatorUserId: (int) $user->id',
            "->event(\n                'access_event_manual_review_recorded'",
            "activity('access_control')",
            'isEligibleRecord(',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }
    }

    public function test_it_does_not_execute_operations_or_device_commands(): void
    {
        $source = $this->sourceOf(
            RecordAccessEventManualReviewAction::class
        );

        foreach ([
            'CheckInVisitUseCase',
            'CheckOutVisitUseCase',
            'ContinueAccessEventFlowUseCase',
            'DecideAccessEventUseCase',
            'RegisterAccessEventOperationalExecutionUseCase',
            'ExecuteAccessEventOperationalExecutionUseCase',
            'Http::',
            'dispatch(',
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

    public function test_the_event_table_exposes_the_action_before_reprocessing(): void
    {
        $source = $this->sourceOf(
            AccessEventRecordsTable::class
        );

        $reviewPosition = strpos(
            $source,
            'RecordAccessEventManualReviewAction::make()'
        );

        $reprocessPosition = strpos(
            $source,
            'ReprocessAccessEventFlowAction::make()'
        );

        $this->assertIsInt($reviewPosition);
        $this->assertIsInt($reprocessPosition);

        $this->assertLessThan(
            $reprocessPosition,
            $reviewPosition
        );
    }

    private function sourceOf(
        string $class
    ): string {
        $filename = (
            new ReflectionClass($class)
        )->getFileName();

        $this->assertIsString($filename);

        $source = file_get_contents(
            $filename
        );

        $this->assertIsString($source);

        return $source;
    }
}
