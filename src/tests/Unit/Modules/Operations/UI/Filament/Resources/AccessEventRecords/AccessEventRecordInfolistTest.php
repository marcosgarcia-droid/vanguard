<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\AccessEventRecords;

use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\AccessEventRecordResource;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Schemas\AccessEventRecordInfolist;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Tables\AccessEventRecordsTable;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class AccessEventRecordInfolistTest extends TestCase
{
    public function test_it_declares_the_expected_read_only_tabs(): void
    {
        $source = $this->sourceOf(
            AccessEventRecordInfolist::class
        );

        foreach ([
            "Tab::make('Evento')",
            "Tab::make('Associação técnica')",
            "Tab::make('Decisão operacional')",
            "Tab::make('Tentativas de execução')",
        ] as $expectedTab) {
            $this->assertStringContainsString(
                $expectedTab,
                $source
            );
        }

        $this->assertStringContainsString(
            'RepeatableEntry::make(',
            $source
        );

        $this->assertStringNotContainsString(
            'raw_payload',
            $source
        );
    }

    public function test_it_presents_the_current_manual_review_status_without_a_new_tab(): void
    {
        $source = $this->sourceOf(
            AccessEventRecordInfolist::class
        );

        foreach ([
            'Section::make(',
            "'Situação da revisão manual'",
            "'manual_review_analysis_status'",
            "'manual_review_reviewed_by'",
            "'manual_review_reviewed_at'",
            "'manual_review_notes'",
            "'manual_review_release_status'",
            "'manual_review_consumed_by'",
            "'manual_review_consumed_at'",
            "'manual_review_next_action'",
            'AccessEventManualReviewStatus::summary(',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        $this->assertStringNotContainsString(
            "Tab::make('Revisão manual')",
            $source
        );

        foreach ([
            "'manual_review_id'",
            "'manual_review_consumption_id'",
        ] as $internalField) {
            $this->assertStringNotContainsString(
                $internalField,
                $source
            );
        }

        $tableSource = $this->sourceOf(
            AccessEventRecordsTable::class
        );

        $this->assertStringContainsString(
            "'latestManualReview.reprocessConsumption'",
            $tableSource
        );
    }

    public function test_the_resource_uses_the_infolist_and_the_table_exposes_only_safe_actions(): void
    {
        $method = new ReflectionMethod(
            AccessEventRecordResource::class,
            'infolist'
        );

        $this->assertSame(
            AccessEventRecordResource::class,
            $method->getDeclaringClass()->getName()
        );

        $tableSource = $this->sourceOf(
            AccessEventRecordsTable::class
        );

        $this->assertStringContainsString(
            'AccessEventActivityLogTimelineAction::make()',
            $tableSource
        );

        $this->assertStringContainsString(
            'ViewAction::make()',
            $tableSource
        );

        $this->assertStringContainsString(
            'ReprocessAccessEventFlowAction::make()',
            $tableSource
        );

        $this->assertStringNotContainsString(
            'EditAction::make()',
            $tableSource
        );

        $this->assertStringNotContainsString(
            'DeleteAction::make()',
            $tableSource
        );
    }

    /**
     * @param  class-string  $class
     */
    private function sourceOf(
        string $class
    ): string {
        $filename = (
            new ReflectionClass($class)
        )->getFileName();

        $this->assertIsString($filename);

        $source = file_get_contents($filename);

        $this->assertIsString($source);

        return $source;
    }
}
