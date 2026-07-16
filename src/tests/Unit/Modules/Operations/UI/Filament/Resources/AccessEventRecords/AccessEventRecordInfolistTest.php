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
