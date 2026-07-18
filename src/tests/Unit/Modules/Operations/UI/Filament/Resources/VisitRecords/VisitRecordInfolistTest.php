<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitRecords;

use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Schemas\VisitRecordInfolist;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Tables\VisitRecordsTable;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\VisitRecordResource;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class VisitRecordInfolistTest extends TestCase
{
    public function test_it_declares_the_expected_visit_tabs_and_operational_sections(): void
    {
        $source = $this->sourceOf(
            VisitRecordInfolist::class
        );

        foreach ([
            "Tab::make('Visita')",
            "Tab::make('Operação')",
            "Tab::make('Observações')",
            "Section::make('Dados da visita')",
            "Section::make('Chegada e identidade')",
            "Section::make('Autorização')",
            "Section::make('Entrada e saída')",
            "Section::make('Encerramento sem acesso')",
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }
    }

    public function test_it_presents_the_complete_operational_history_without_internal_ids(): void
    {
        $source = $this->sourceOf(
            VisitRecordInfolist::class
        );

        foreach ([
            "'arrived_at'",
            "'identity_verified_at'",
            "'authorizerEmployee.full_name'",
            "'authorization_method'",
            "'authorized_at'",
            "'checked_in_at'",
            "'checked_out_at'",
            "'rejected_at'",
            "'rejection_reason'",
            "'cancelled_at'",
            "'cancellation_reason'",
        ] as $expectedField) {
            $this->assertStringContainsString(
                $expectedField,
                $source
            );
        }

        foreach ([
            "TextEntry::make('id')",
            "TextEntry::make('tenant_id')",
            "TextEntry::make('organization_id')",
            "TextEntry::make('visitor_id')",
            "TextEntry::make('host_employee_id')",
        ] as $internalField) {
            $this->assertStringNotContainsString(
                $internalField,
                $source
            );
        }
    }

    public function test_the_resource_uses_the_infolist_and_the_table_exposes_only_safe_actions(): void
    {
        $method = new ReflectionMethod(
            VisitRecordResource::class,
            'infolist'
        );

        $this->assertSame(
            VisitRecordResource::class,
            $method->getDeclaringClass()->getName()
        );

        $tableSource = $this->sourceOf(
            VisitRecordsTable::class
        );

        foreach ([
            'VanguardActivityLogTimelineAction::make()',
            'ViewAction::make()',
            'RegisterVisitArrivalAction::make()',
            'AuthorizeVisitAction::make()',
            'RejectVisitAction::make()',
            'CheckInVisitAction::make()',
            'CheckOutVisitAction::make()',
            'CancelVisitAction::make()',
        ] as $expectedAction) {
            $this->assertStringContainsString(
                $expectedAction,
                $tableSource
            );
        }

        foreach ([
            'EditAction::make()',
            'DeleteAction::make()',
            'ForceDeleteAction::make()',
            'RestoreAction::make()',
            'BulkActionGroup::make(',
        ] as $unsafeAction) {
            $this->assertStringNotContainsString(
                $unsafeAction,
                $tableSource
            );
        }
    }

    /**
     * @param  class-string  $class
     */
    private function sourceOf(string $class): string
    {
        $filename = (
            new ReflectionClass($class)
        )->getFileName();

        $this->assertIsString($filename);

        $source = file_get_contents($filename);

        $this->assertIsString($source);

        return $source;
    }
}
