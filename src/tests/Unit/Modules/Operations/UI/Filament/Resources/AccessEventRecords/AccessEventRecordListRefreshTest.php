<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\AccessEventRecords;

use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Pages\ListAccessEventRecords;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Tables\AccessEventRecordsTable;
use ReflectionClass;
use Tests\TestCase;

class AccessEventRecordListRefreshTest extends TestCase
{
    public function test_it_declares_a_safe_manual_list_refresh(): void
    {
        $reflection = new ReflectionClass(
            ListAccessEventRecords::class
        );

        $source = file_get_contents(
            (string) $reflection->getFileName()
        );

        $this->assertIsString($source);

        foreach ([
            "Action::make('refreshAccessEventList')",
            "->label('Atualizar listagem')",
            '$this->flushCachedTableRecords();',
            "->title('Listagem atualizada')",
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        foreach ([
            '$this->resetTable();',
            '$this->resetPage();',
            'ContinueAccessEventFlowUseCase',
            'ReprocessAccessEventFlowAction',
            'Http::',
            '::create(',
            '->save(',
            '->update(',
            'dispatch(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_it_disables_event_list_polling_when_the_flag_is_off(): void
    {
        config()->set(
            'access_control.event_list_polling_enabled',
            false
        );

        config()->set(
            'access_control.event_list_polling_interval_seconds',
            30
        );

        $this->assertNull(
            AccessEventRecordsTable::pollingInterval()
        );
    }

    public function test_it_limits_the_polling_interval_to_safe_bounds(): void
    {
        config()->set(
            'access_control.event_list_polling_enabled',
            true
        );

        config()->set(
            'access_control.event_list_polling_interval_seconds',
            1
        );

        $this->assertSame(
            '30s',
            AccessEventRecordsTable::pollingInterval()
        );

        config()->set(
            'access_control.event_list_polling_interval_seconds',
            45
        );

        $this->assertSame(
            '45s',
            AccessEventRecordsTable::pollingInterval()
        );

        config()->set(
            'access_control.event_list_polling_interval_seconds',
            999
        );

        $this->assertSame(
            '300s',
            AccessEventRecordsTable::pollingInterval()
        );
    }

    public function test_it_connects_the_optional_polling_to_the_table(): void
    {
        $reflection = new ReflectionClass(
            AccessEventRecordsTable::class
        );

        $source = file_get_contents(
            (string) $reflection->getFileName()
        );

        $this->assertIsString($source);

        $this->assertStringContainsString(
            'self::pollingInterval()',
            $source
        );

        $this->assertStringNotContainsString(
            "->poll('",
            $source
        );
    }

    public function test_it_documents_polling_as_disabled_by_default(): void
    {
        $environment = file_get_contents(
            base_path('.env.example')
        );

        $this->assertIsString($environment);

        $this->assertStringContainsString(
            'VANGUARD_ACCESS_CONTROL_EVENT_LIST_POLLING_ENABLED=false',
            $environment
        );

        $this->assertStringContainsString(
            'VANGUARD_ACCESS_CONTROL_EVENT_LIST_POLLING_INTERVAL_SECONDS=30',
            $environment
        );
    }
}
