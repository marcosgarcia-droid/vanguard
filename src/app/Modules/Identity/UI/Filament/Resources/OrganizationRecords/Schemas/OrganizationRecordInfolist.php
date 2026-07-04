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
                    ->label('ID'),
                TextEntry::make('status'),
                TextEntry::make('cnpj')
                    ->placeholder('-'),
                TextEntry::make('cnpj_formatted')
                    ->placeholder('-'),
                TextEntry::make('cnpj_root')
                    ->placeholder('-'),
                TextEntry::make('cnpj_branch')
                    ->placeholder('-'),
                TextEntry::make('cnpj_check_digits')
                    ->placeholder('-'),
                TextEntry::make('legal_name'),
                TextEntry::make('trade_name')
                    ->placeholder('-'),
                TextEntry::make('establishment_type')
                    ->placeholder('-'),
                IconEntry::make('is_head_office')
                    ->boolean()
                    ->placeholder('-'),
                TextEntry::make('head_office_organization_id')
                    ->placeholder('-'),
                TextEntry::make('opened_at')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('closed_at')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('legal_nature_code')
                    ->placeholder('-'),
                TextEntry::make('legal_nature_name')
                    ->placeholder('-'),
                TextEntry::make('company_size_code')
                    ->placeholder('-'),
                TextEntry::make('company_size_name')
                    ->placeholder('-'),
                TextEntry::make('share_capital')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('tax_registration_status_code')
                    ->placeholder('-'),
                TextEntry::make('tax_registration_status_name')
                    ->placeholder('-'),
                TextEntry::make('tax_registration_status_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('tax_registration_status_reason')
                    ->placeholder('-'),
                TextEntry::make('special_status')
                    ->placeholder('-'),
                TextEntry::make('special_status_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('responsible_federative_entity')
                    ->placeholder('-'),
                TextEntry::make('cnpj_synced_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('cnpj_sync_provider')
                    ->placeholder('-'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (OrganizationRecord $record): bool => $record->trashed()),
            ]);
    }
}
