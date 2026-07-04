<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Schemas;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrganizationRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('Código interno'),

                TextEntry::make('status')
                    ->label('Status'),

                TextEntry::make('cnpj_formatted')
                    ->label('CNPJ')
                    ->placeholder('-'),

                TextEntry::make('cnpj_root')
                    ->label('Raiz do CNPJ')
                    ->placeholder('-'),

                TextEntry::make('legal_name')
                    ->label('Razão social'),

                TextEntry::make('trade_name')
                    ->label('Nome fantasia')
                    ->placeholder('-'),

                TextEntry::make('establishment_type')
                    ->label('Tipo de estabelecimento')
                    ->placeholder('-'),

                IconEntry::make('is_head_office')
                    ->label('Matriz')
                    ->boolean()
                    ->placeholder('-'),

                TextEntry::make('head_office_organization_id')
                    ->label('Código da matriz')
                    ->placeholder('-'),

                TextEntry::make('opened_at')
                    ->label('Data de abertura')
                    ->date()
                    ->placeholder('-'),

                TextEntry::make('closed_at')
                    ->label('Data de encerramento')
                    ->date()
                    ->placeholder('-'),

                TextEntry::make('legal_nature_code')
                    ->label('Código da natureza jurídica')
                    ->placeholder('-'),

                TextEntry::make('legal_nature_name')
                    ->label('Natureza jurídica')
                    ->placeholder('-'),

                TextEntry::make('company_size_code')
                    ->label('Código do porte')
                    ->placeholder('-'),

                TextEntry::make('company_size_name')
                    ->label('Porte')
                    ->placeholder('-'),

                TextEntry::make('share_capital')
                    ->label('Capital social')
                    ->money('BRL')
                    ->placeholder('-'),

                TextEntry::make('tax_registration_status_code')
                    ->label('Código da situação cadastral')
                    ->placeholder('-'),

                TextEntry::make('tax_registration_status_name')
                    ->label('Situação cadastral')
                    ->placeholder('-'),

                TextEntry::make('tax_registration_status_date')
                    ->label('Data da situação cadastral')
                    ->date()
                    ->placeholder('-'),

                TextEntry::make('tax_registration_status_reason')
                    ->label('Motivo da situação cadastral')
                    ->placeholder('-'),

                TextEntry::make('special_status')
                    ->label('Situação especial')
                    ->placeholder('-'),

                TextEntry::make('special_status_date')
                    ->label('Data da situação especial')
                    ->date()
                    ->placeholder('-'),

                TextEntry::make('responsible_federative_entity')
                    ->label('Ente federativo responsável')
                    ->placeholder('-'),

                TextEntry::make('cnpj_synced_at')
                    ->label('Sincronizado em')
                    ->dateTime()
                    ->placeholder('-'),

                TextEntry::make('cnpj_sync_provider')
                    ->label('Provider da última sincronização')
                    ->placeholder('-'),

                TextEntry::make('notes')
                    ->label('Observações')
                    ->placeholder('-')
                    ->columnSpanFull(),

                TextEntry::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->placeholder('-'),

                TextEntry::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime()
                    ->placeholder('-'),

                TextEntry::make('deleted_at')
                    ->label('Excluído em')
                    ->dateTime()
                    ->visible(fn (OrganizationRecord $record): bool => $record->trashed()),
            ]);
    }
}
