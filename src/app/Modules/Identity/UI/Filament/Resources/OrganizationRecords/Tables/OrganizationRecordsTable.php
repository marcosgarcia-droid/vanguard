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

                TextColumn::make('cnpj_formatted')
                    ->label('CNPJ')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('tax_registration_status_name')
                    ->label('Situação CNPJ')
                    ->badge()
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('company_size_name')
                    ->label('Porte')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('cnpj_sync_provider')
                    ->label('Provider')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('cnpj_synced_at')
                    ->label('Sincronizado em')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                ViewAction::make(),
                EditAction::make(),
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
