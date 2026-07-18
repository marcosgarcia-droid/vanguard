<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitRecords;

use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Pages\KanbanVisitRecords;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Pages\ListVisitRecords;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\VisitRecordResource;
use ReflectionClass;
use Tests\TestCase;
use Wezlo\FilamentKanban\Concerns\HasKanbanBoard;

class VisitRecordKanbanTest extends TestCase
{
    public function test_it_uses_a_kanban_as_the_main_page_and_keeps_the_list(): void
    {
        $pages = VisitRecordResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('list', $pages);

        $this->assertSame(
            KanbanVisitRecords::class,
            $pages['index']->getPage()
        );

        $this->assertSame(
            ListVisitRecords::class,
            $pages['list']->getPage()
        );
    }

    public function test_the_kanban_does_not_inherit_the_list_tabs(): void
    {
        $page = app(
            KanbanVisitRecords::class
        );

        $this->assertSame(
            [],
            $page->getTabs()
        );
    }

    public function test_the_kanban_is_read_only_and_uses_vanguard_views(): void
    {
        $source = $this->sourceOf(
            KanbanVisitRecords::class
        );

        foreach ([
            'use HasKanbanBoard;',
            '->boardView(',
            "'filament.resources.visit-records.kanban.board'",
            '->columnView(',
            "'filament.resources.visit-records.kanban.column'",
            '->cardView(',
            "'filament.resources.visit-records.kanban.card'",
            '->canMove(fn (): bool => false)',
            'VisitStatus::Rejected',
            'VisitStatus::Cancelled',
            'VisitStatus::Expired',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        $this->assertContains(
            HasKanbanBoard::class,
            class_uses_recursive(
                KanbanVisitRecords::class
            )
        );
    }

    public function test_custom_board_does_not_initialize_drag_and_drop(): void
    {
        $board = resource_path(
            'views/filament/resources/visit-records/kanban/board.blade.php'
        );

        $column = resource_path(
            'views/filament/resources/visit-records/kanban/column.blade.php'
        );

        $this->assertFileExists($board);
        $this->assertFileExists($column);

        $source = file_get_contents($board)
            .PHP_EOL
            .file_get_contents($column);

        foreach ([
            'Sortable',
            'x-load-src',
            'moveRecord(',
            'data-kanban-column',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }

        $this->assertStringContainsString(
            'A movimentação por arrastar está desabilitada.',
            $source
        );
    }

    public function test_the_card_shows_photo_operational_data_and_safe_actions(): void
    {
        $card = resource_path(
            'views/filament/resources/visit-records/kanban/card.blade.php'
        );

        $this->assertFileExists($card);

        $source = file_get_contents($card);

        $this->assertIsString($source);

        foreach ([
            'visitorPhotoUrl($record)',
            'visitorInitials($record)',
            'Foto de {{ $visitorName }}',
            'Visitado',
            'Finalidade',
            'Previsão',
            'Unidade',
            'CHEGADA REGISTRADA',
            'DENTRO DA UNIDADE',
            'SAÍDA REGISTRADA',
            "(\$this->viewVisitAction)(['record' => \$record->getKey()])",
            "(\$this->registerVisitArrivalAction)(['record' => \$record->getKey()])",
            "(\$this->authorizeVisitAction)(['record' => \$record->getKey()])",
            "(\$this->rejectVisitAction)(['record' => \$record->getKey()])",
            "(\$this->checkInVisitAction)(['record' => \$record->getKey()])",
            "(\$this->checkOutVisitAction)(['record' => \$record->getKey()])",
            "(\$this->cancelVisitAction)(['record' => \$record->getKey()])",
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }
        foreach ([
            'EditAction::make()',
            'DeleteAction::make()',
            'wire:sort',
            'x-sortable',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_the_kanban_filters_by_the_operational_statuses(): void
    {
        $source = $this->sourceOf(
            KanbanVisitRecords::class
        );

        foreach ([
            "Select::make('status')",
            "->label('Situação')",
            'VisitStatus::Scheduled->value',
            'VisitStatus::PendingAuthorization->value',
            'VisitStatus::Authorized->value',
            'VisitStatus::InProgress->value',
            'VisitStatus::Completed->value',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        $this->assertStringNotContainsString(
            "Select::make('organization_id')",
            $source
        );

        $this->assertStringNotContainsString(
            'organizationOptions()',
            $source
        );
    }

    public function test_the_view_action_uses_the_visit_infolist(): void
    {
        $source = $this->sourceOf(
            KanbanVisitRecords::class
        );

        foreach ([
            "Action::make('viewVisit')",
            'VisitRecordResource::infolist(',
            '->modalSubmitAction(false)',
            "->modalCancelActionLabel('Fechar')",
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }
    }

    /**
     * @param  class-string  $class
     */
    public function test_card_invokes_registered_actions_with_the_visit_argument(): void
    {
        $source = file_get_contents(
            resource_path(
                'views/filament/resources/visit-records/kanban/card.blade.php'
            )
        );

        $this->assertIsString($source);

        foreach ([
            'registerVisitArrivalAction',
            'authorizeVisitAction',
            'rejectVisitAction',
            'checkInVisitAction',
            'checkOutVisitAction',
            'cancelVisitAction',
        ] as $action) {
            $this->assertStringContainsString(
                sprintf(
                    "{{ (\$this->%s)(['record' => \$record->getKey()]) }}",
                    $action
                ),
                $source
            );
        }

        $this->assertStringNotContainsString(
            '->record($record)',
            $source
        );
    }

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
