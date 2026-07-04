<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Tables;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Actions\SyncOrganizationCnpjAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrganizationRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['addresses', 'contacts']))
            ->defaultSort('display_name')
            ->columns([
                TextColumn::make('display_name')
                    ->label('Unidade')
                    ->formatStateUsing(fn (?string $state, OrganizationRecord $record): string => $record->operational_name)
                    ->searchable(['display_name', 'unit_code', 'legal_name', 'trade_name', 'cnpj', 'cnpj_formatted'])
                    ->sortable(),

                TextColumn::make('unit_code')
                    ->label('Código')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('city_state')
                    ->label('Cidade/UF')
                    ->placeholder('-'),

                TextColumn::make('cnpj')
                    ->label('CNPJ')
                    ->formatStateUsing(fn (?string $state): string => self::formatCnpj($state))
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('tax_registration_status_name')
                    ->label('Situação')
                    ->badge()
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('primary_contact_display')
                    ->label('Contato')
                    ->placeholder('-'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                SyncOrganizationCnpjAction::make(),

                ViewAction::make()
                    ->label('Visualizar')
                    ->tooltip('Visualizar')
                    ->iconButton()
                    ->modalHeading('Visualizar organização'),

                EditAction::make()
                    ->label('Editar')
                    ->tooltip('Editar')
                    ->iconButton()
                    ->modalHeading('Editar organização')
                    ->modalSubmitActionLabel('Salvar alterações')
                    ->successNotificationTitle('Organização atualizada')
                    ->extraModalFooterActions([
                        SyncOrganizationCnpjAction::make('syncOrganizationCnpjFromEditModal', iconButton: false),
                    ]),

                DeleteAction::make()
                    ->label('Excluir')
                    ->tooltip('Excluir')
                    ->iconButton()
                    ->modalHeading('Excluir organização')
                    ->modalDescription('A organização será movida para a lixeira e poderá ser restaurada posteriormente.')
                    ->modalSubmitActionLabel('Excluir')
                    ->successNotificationTitle('Organização excluída'),

                RestoreAction::make()
                    ->label('Restaurar')
                    ->tooltip('Restaurar')
                    ->iconButton()
                    ->modalHeading('Restaurar organização')
                    ->modalDescription('A organização voltará a aparecer normalmente na listagem.')
                    ->modalSubmitActionLabel('Restaurar')
                    ->successNotificationTitle('Organização restaurada'),

                ForceDeleteAction::make()
                    ->label('Excluir definitivamente')
                    ->tooltip('Excluir definitivamente')
                    ->iconButton()
                    ->modalHeading('Excluir organização definitivamente')
                    ->modalDescription('Esta ação não poderá ser desfeita.')
                    ->modalSubmitActionLabel('Excluir definitivamente')
                    ->successNotificationTitle('Organização excluída definitivamente'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    private static function formatCnpj(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (strlen($digits) !== 14) {
            return $value ?: '-';
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 4),
            substr($digits, 12, 2),
        );
    }
}
