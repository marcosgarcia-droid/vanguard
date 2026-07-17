<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions;

use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions\ContinueManuallyAssociatedAccessEventFlowAction;
use Filament\Actions\Action;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class ContinueManuallyAssociatedAccessEventFlowActionTest extends TestCase
{
    public function test_it_creates_the_continuation_action(): void
    {
        $action =
            ContinueManuallyAssociatedAccessEventFlowAction::make();

        $this->assertInstanceOf(
            Action::class,
            $action
        );

        $this->assertSame(
            'continueManuallyAssociatedAccessEventFlow',
            $action->getName()
        );
    }

    public function test_it_accepts_only_complete_manual_associations_that_have_not_continued_yet(): void
    {
        $eligible = new AccessEventRecord;

        $eligible->forceFill([
            'status' => AccessEventStatus::Processed,
            'result_code' => 'manual_association_completed',
            'visitor_id' => (string) Str::uuid(),
            'visit_id' => (string) Str::uuid(),
            'operational_decisions_count' => 0,
            'operational_executions_count' => 0,
        ]);

        $partial = new AccessEventRecord;

        $partial->forceFill([
            'status' => AccessEventStatus::PendingAssociation,
            'result_code' => 'manual_visitor_association_pending_visit',
            'visitor_id' => (string) Str::uuid(),
            'visit_id' => null,
            'operational_decisions_count' => 0,
            'operational_executions_count' => 0,
        ]);

        $automatic = new AccessEventRecord;

        $automatic->forceFill([
            'status' => AccessEventStatus::Processed,
            'result_code' => 'association_completed',
            'visitor_id' => (string) Str::uuid(),
            'visit_id' => (string) Str::uuid(),
            'operational_decisions_count' => 0,
            'operational_executions_count' => 0,
        ]);

        $alreadyContinued = new AccessEventRecord;

        $alreadyContinued->forceFill([
            'status' => AccessEventStatus::Processed,
            'result_code' => 'manual_association_completed',
            'visitor_id' => (string) Str::uuid(),
            'visit_id' => (string) Str::uuid(),
            'operational_decisions_count' => 1,
            'operational_executions_count' => 1,
        ]);

        $this->assertTrue(
            ContinueManuallyAssociatedAccessEventFlowAction::isEligibleRecord(
                $eligible
            )
        );

        $this->assertFalse(
            ContinueManuallyAssociatedAccessEventFlowAction::isEligibleRecord(
                $partial
            )
        );

        $this->assertFalse(
            ContinueManuallyAssociatedAccessEventFlowAction::isEligibleRecord(
                $automatic
            )
        );

        $this->assertFalse(
            ContinueManuallyAssociatedAccessEventFlowAction::isEligibleRecord(
                $alreadyContinued
            )
        );
    }

    public function test_it_authorizes_calls_the_controlled_use_case_and_audits(): void
    {
        $filename = (
            new ReflectionClass(
                ContinueManuallyAssociatedAccessEventFlowAction::class
            )
        )->getFileName();

        $this->assertIsString($filename);

        $source = file_get_contents($filename);

        $this->assertIsString($source);

        foreach ([
            'Gate::authorize(',
            "'reprocessFlow'",
            'ContinueManuallyAssociatedAccessEventFlowUseCase::class',
            'ContinueManuallyAssociatedAccessEventFlowCommand(',
            'operatorUserId:',
            "->event(\n                'access_event_manual_association_flow_continued'",
            "activity('access_control')",
            'requiresConfirmation()',
            'isEligibleRecord(',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }
    }

    public function test_it_does_not_bypass_the_controlled_pipeline_or_send_device_commands(): void
    {
        $filename = (
            new ReflectionClass(
                ContinueManuallyAssociatedAccessEventFlowAction::class
            )
        )->getFileName();

        $this->assertIsString($filename);

        $source = file_get_contents($filename);

        $this->assertIsString($source);

        foreach ([
            'ContinueAccessEventFlowUseCase::class',
            'CheckInVisitUseCase',
            'CheckOutVisitUseCase',
            'Http::',
            'dispatch(',
            'raw_payload',
            'openDoor',
            'unlock',
            'setConfig',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_the_event_table_exposes_the_continuation_action(): void
    {
        $table = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/AccessEventRecords/Tables/AccessEventRecordsTable.php'
            )
        );

        $this->assertIsString($table);

        $this->assertStringContainsString(
            'ContinueManuallyAssociatedAccessEventFlowAction::make()',
            $table
        );
    }
}
