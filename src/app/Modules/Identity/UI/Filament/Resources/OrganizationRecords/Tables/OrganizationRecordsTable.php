<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Tables;

use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Actions\SyncOrganizationCnpjAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class OrganizationRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('legal_name')
            ->columns([
                TextColumn::make('legal_name')
                    ->label('Razão social')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('trade_name')
                    ->label('Nome fantasia')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

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

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->successNotificationTitle('Organização atualizada'),
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
