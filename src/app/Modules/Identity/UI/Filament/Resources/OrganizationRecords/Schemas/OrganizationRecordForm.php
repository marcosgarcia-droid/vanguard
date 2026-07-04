<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OrganizationRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('status')
                    ->required()
                    ->default('active'),
                TextInput::make('cnpj'),
                TextInput::make('cnpj_formatted'),
                TextInput::make('cnpj_root'),
                TextInput::make('cnpj_branch'),
                TextInput::make('cnpj_check_digits'),
                TextInput::make('legal_name')
                    ->required(),
                TextInput::make('trade_name'),
                TextInput::make('establishment_type'),
                Toggle::make('is_head_office'),
                TextInput::make('head_office_organization_id'),
                DatePicker::make('opened_at'),
                DatePicker::make('closed_at'),
                TextInput::make('legal_nature_code'),
                TextInput::make('legal_nature_name'),
                TextInput::make('company_size_code'),
                TextInput::make('company_size_name'),
                TextInput::make('share_capital')
                    ->numeric(),
                TextInput::make('tax_registration_status_code'),
                TextInput::make('tax_registration_status_name'),
                DatePicker::make('tax_registration_status_date'),
                TextInput::make('tax_registration_status_reason'),
                TextInput::make('special_status'),
                DatePicker::make('special_status_date'),
                TextInput::make('responsible_federative_entity'),
                DateTimePicker::make('cnpj_synced_at'),
                TextInput::make('cnpj_sync_provider'),
                TextInput::make('cnpj_normalized_data'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
