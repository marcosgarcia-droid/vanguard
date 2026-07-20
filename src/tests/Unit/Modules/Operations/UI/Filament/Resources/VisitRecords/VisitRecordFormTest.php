<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitRecords;

use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Pages\ListVisitRecords;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Schemas\VisitRecordForm;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Tables\VisitRecordsTable;
use ReflectionClass;
use Tests\TestCase;

class VisitRecordFormTest extends TestCase
{
    public function test_it_declares_the_operational_visit_scheduling_form(): void
    {
        $source = $this->sourceOf(
            VisitRecordForm::class
        );

        foreach ([
            "Tab::make('Visita')",
            "Tab::make('Data e horário')",
            "Tab::make('Observações')",
            "Hidden::make('tenant_id')",
            "Hidden::make('status')",
            "Select::make('organization_id')",
            "Select::make('visitor_id')",
            "Select::make('host_employee_id')",
            "Select::make('partner_id')",
            "TextInput::make('purpose')",
            "DateTimePicker::make('expected_start_at')",
            "DateTimePicker::make('expected_end_at')",
            "Textarea::make('notes')",
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        $this->assertStringContainsString(
            'VisitStatus::Scheduled->value',
            $source
        );

        foreach ([
            'authorized_at',
            'rejected_at',
            'checked_in_at',
            'checked_out_at',
            'cancelled_at',
        ] as $operationalField) {
            $this->assertStringNotContainsString(
                "make('{$operationalField}')",
                $source
            );
        }
    }

    public function test_it_uses_visited_instead_of_host_in_the_user_interface(): void
    {
        $files = [
            app_path(
                'Modules/Operations/UI/Filament/Resources/VisitRecords/Schemas/VisitRecordForm.php'
            ),
            app_path(
                'Modules/Operations/UI/Filament/Resources/VisitRecords/Schemas/VisitRecordInfolist.php'
            ),
            app_path(
                'Modules/Operations/UI/Filament/Resources/VisitRecords/Tables/VisitRecordsTable.php'
            ),
            app_path(
                'Modules/Operations/UI/Filament/Resources/VisitRecords/Pages/ListVisitRecords.php'
            ),
        ];

        foreach ($files as $file) {
            $source = file_get_contents($file);

            $this->assertIsString($source);

            $this->assertStringNotContainsString(
                'Anfitrião',
                $source
            );

            $this->assertStringNotContainsString(
                'anfitrião',
                $source
            );
        }

        $combinedSource = implode(
            PHP_EOL,
            array_map(
                fn (string $file): string => (string) file_get_contents($file),
                $files
            )
        );

        $this->assertStringContainsString(
            "->label('Visitado')",
            $combinedSource
        );

        $this->assertStringContainsString(
            'O visitado selecionado não está disponível para este grupo empresarial.',
            $combinedSource
        );

        $this->assertStringContainsString(
            "Select::make('host_employee_id')",
            $combinedSource
        );
    }

    public function test_the_list_declares_safe_creation_and_no_direct_mutation_actions(): void
    {
        $pageSource = $this->sourceOf(
            ListVisitRecords::class
        );

        $this->assertStringContainsString(
            'CreateAction::make()',
            $pageSource
        );

        $this->assertStringContainsString(
            "'status'] = VisitStatus::Scheduled->value",
            $pageSource
        );

        foreach ([
            'validateVisitor(',
            'validateEmployee(',
            'validatePartner(',
            'hasOrganizationAccess(',
        ] as $validation) {
            $this->assertStringContainsString(
                $validation,
                $pageSource
            );
        }

        $tableSource = $this->sourceOf(
            VisitRecordsTable::class
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
