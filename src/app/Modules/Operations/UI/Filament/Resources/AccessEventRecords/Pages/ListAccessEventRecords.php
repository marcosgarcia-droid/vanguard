<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Pages;

use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\AccessEventRecordResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAccessEventRecords extends ListRecords
{
    protected static string $resource =
        AccessEventRecordResource::class;

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
         * Pesquisa, filtros, ordenação e paginação permanecem
         * inalterados.
         */
        $this->flushCachedTableRecords();

        Notification::make()
            ->title('Listagem atualizada')
            ->body(
                'Os eventos mais recentes foram carregados sem alterar a pesquisa e os filtros.'
            )
            ->success()
            ->send();
    }
}
