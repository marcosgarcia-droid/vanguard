<?php

namespace App\Modules\Identity\UI\Filament\Resources\ClassificationOptionRecords\Tables;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecord;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClassificationOptionRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => app(TenantContext::class)
                ->applyTenantScope($query, auth()->user()))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('category_display')
                    ->label('Categoria')
                    ->searchable(['category'])
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('category', $direction)),

                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('status_display')
                    ->label('Status')
                    ->badge()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('status', $direction)),

                TextColumn::make('sort_order')
                    ->label('Ordem')
                    ->sortable(),

                IconColumn::make('is_system')
                    ->label('Padrão do sistema')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Categoria')
                    ->options([
                        'partner_profile' => 'Perfil de parceiro',
                        'partner_document_type' => 'Tipo de documento de parceiro',
                        'partner_contact_type' => 'Tipo de contato de parceiro',
                        'partner_address_type' => 'Tipo de endereço de parceiro',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Visualizar')
                    ->tooltip('Visualizar')
                    ->iconButton()
                    ->modalHeading(fn (ClassificationOptionRecord $record): string => 'Visualizar classificação - '.$record->name)
                    ->modalWidth(Width::SevenExtraLarge),

                EditAction::make()
                    ->label('Editar')
                    ->tooltip('Editar')
                    ->iconButton()
                    ->modalHeading(fn (ClassificationOptionRecord $record): string => 'Editar classificação - '.$record->name)
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalSubmitActionLabel('Salvar alterações')
                    ->successNotificationTitle('Classificação atualizada'),

                DeleteAction::make()
                    ->label('Excluir')
                    ->tooltip('Excluir')
                    ->iconButton()
                    ->visible(fn (ClassificationOptionRecord $record): bool => ! $record->is_system)
                    ->modalHeading('Excluir classificação')
                    ->modalDescription('A classificação será movida para a lixeira e poderá ser restaurada posteriormente.')
                    ->modalSubmitActionLabel('Excluir')
                    ->successNotificationTitle('Classificação excluída'),

                RestoreAction::make()
                    ->label('Restaurar')
                    ->tooltip('Restaurar')
                    ->iconButton()
                    ->modalHeading('Restaurar classificação')
                    ->modalDescription('A classificação voltará a aparecer normalmente na listagem.')
                    ->modalSubmitActionLabel('Restaurar')
                    ->successNotificationTitle('Classificação restaurada'),

                ForceDeleteAction::make()
                    ->label('Excluir definitivamente')
                    ->tooltip('Excluir definitivamente')
                    ->iconButton()
                    ->visible(fn (ClassificationOptionRecord $record): bool => ! $record->is_system)
                    ->modalHeading('Excluir classificação definitivamente')
                    ->modalDescription('Esta ação não poderá ser desfeita.')
                    ->modalSubmitActionLabel('Excluir definitivamente')
                    ->successNotificationTitle('Classificação excluída definitivamente'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
