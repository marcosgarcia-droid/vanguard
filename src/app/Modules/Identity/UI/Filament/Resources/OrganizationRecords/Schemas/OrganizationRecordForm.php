<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class OrganizationRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id')
                    ->label('Código interno')
                    ->default(fn (): string => (string) Str::uuid())
                    ->required()
                    ->maxLength(255),

                TextInput::make('status')
                    ->label('Status')
                    ->required()
                    ->default('active')
                    ->maxLength(255),

                TextInput::make('cnpj')
                    ->label('CNPJ')
                    ->helperText('Informe somente números ou use o formato 00.000.000/0000-00.')
                    ->maxLength(18),

                TextInput::make('legal_name')
                    ->label('Razão social')
                    ->required()
                    ->maxLength(255),

                TextInput::make('trade_name')
                    ->label('Nome fantasia')
                    ->maxLength(255),

                TextInput::make('establishment_type')
                    ->label('Tipo de estabelecimento')
                    ->maxLength(255),

                Toggle::make('is_head_office')
                    ->label('Matriz'),

                TextInput::make('head_office_organization_id')
                    ->label('Código da matriz')
                    ->maxLength(255),

                DatePicker::make('opened_at')
                    ->label('Data de abertura'),

                DatePicker::make('closed_at')
                    ->label('Data de encerramento'),

                TextInput::make('legal_nature_code')
                    ->label('Código da natureza jurídica')
                    ->maxLength(255),

                TextInput::make('legal_nature_name')
                    ->label('Natureza jurídica')
                    ->maxLength(255),

                TextInput::make('company_size_code')
                    ->label('Código do porte')
                    ->maxLength(255),

                TextInput::make('company_size_name')
                    ->label('Porte')
                    ->maxLength(255),

                TextInput::make('share_capital')
                    ->label('Capital social')
                    ->numeric(),

                TextInput::make('tax_registration_status_code')
                    ->label('Código da situação cadastral')
                    ->maxLength(255),

                TextInput::make('tax_registration_status_name')
                    ->label('Situação cadastral')
                    ->maxLength(255),

                DatePicker::make('tax_registration_status_date')
                    ->label('Data da situação cadastral'),

                TextInput::make('tax_registration_status_reason')
                    ->label('Motivo da situação cadastral')
                    ->maxLength(255),

                TextInput::make('special_status')
                    ->label('Situação especial')
                    ->maxLength(255),

                DatePicker::make('special_status_date')
                    ->label('Data da situação especial'),

                TextInput::make('responsible_federative_entity')
                    ->label('Ente federativo responsável')
                    ->maxLength(255),

                DateTimePicker::make('cnpj_synced_at')
                    ->label('Sincronizado em')
                    ->disabled(),

                TextInput::make('cnpj_sync_provider')
                    ->label('Provider da última sincronização')
                    ->disabled(),

                Textarea::make('notes')
                    ->label('Observações')
                    ->columnSpanFull(),
            ]);
    }
}
