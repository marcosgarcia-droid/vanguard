<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Pages;

use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\AccessEventRecordResource;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Tables\AccessEventRecordsTable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAccessEventRecords extends ListRecords
{
    protected static string $resource =
        AccessEventRecordResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos'),

            'pending_association' => Tab::make(
                'Aguardando associação'
            )
                ->modifyQueryUsing(
                    fn (
                        Builder $query
                    ): Builder => AccessEventRecordsTable::applyEventStatusFilter(
                        $query,
                        AccessEventStatus::PendingAssociation->value
                    )
                ),

            'manual_review' => Tab::make(
                'Revisão manual'
            )
                ->modifyQueryUsing(
                    fn (
                        Builder $query
                    ): Builder => AccessEventRecordsTable::applyLatestDecisionFilter(
                        $query,
                        AccessEventOperationalDecision::ManualReview->value
                    )
                ),

            'blocked_attempts' => Tab::make(
                'Tentativas bloqueadas'
            )
                ->modifyQueryUsing(
                    fn (
                        Builder $query
                    ): Builder => AccessEventRecordsTable::applyLatestExecutionStatusFilter(
                        $query,
                        AccessEventOperationalExecutionStatus::Blocked->value
                    )
                ),

            'failed' => Tab::make('Falhas')
                ->modifyQueryUsing(
                    fn (
                        Builder $query
                    ): Builder => AccessEventRecordsTable::applyEventStatusFilter(
                        $query,
                        AccessEventStatus::Failed->value
                    )
                ),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshAccessEventList')
                ->label('Atualizar listagem')
                ->tooltip(
                    'Carregar os eventos mais recentes'
                )
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $this->refreshAccessEventList();
                }),
        ];
    }

    public function refreshAccessEventList(): void
    {
        /*
         * Limpa somente os registros consultados nesta renderização.
         *
         * Pesquisa, filtros, ordenação, paginação e aba ativa
         * permanecem inalterados.
         */
        $this->flushCachedTableRecords();

        Notification::make()
            ->title('Listagem atualizada')
            ->body(
                'Os eventos mais recentes foram carregados sem alterar a pesquisa, os filtros e a aba selecionada.'
            )
            ->success()
            ->send();
    }
}
