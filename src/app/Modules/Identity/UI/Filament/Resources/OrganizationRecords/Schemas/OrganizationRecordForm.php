<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
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
            ->columns(6)
            ->components([
                Hidden::make('id')
                    ->default(fn (): string => (string) Str::uuid())
                    ->required(),

                TextInput::make('cnpj')
                    ->label('CNPJ')
                    ->helperText('Informe somente números ou use o formato 00.000.000/0000-00.')
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                        ? preg_replace('/\D+/', '', $state)
                        : null)
                    ->maxLength(18)
                    ->columnSpan(3),

                TextInput::make('tax_registration_status_name')
                    ->label('Situação cadastral')
                    ->maxLength(255)
                    ->columnSpan(3),

                TextInput::make('legal_name')
                    ->label('Razão social')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(3),

                TextInput::make('trade_name')
                    ->label('Nome fantasia')
                    ->maxLength(255)
                    ->columnSpan(3),

                TextInput::make('establishment_type')
                    ->label('Tipo de estabelecimento')
                    ->maxLength(255)
                    ->columnSpan(3),

                TextInput::make('legal_nature_name')
                    ->label('Natureza jurídica')
                    ->maxLength(255)
                    ->columnSpan(3),

                DatePicker::make('opened_at')
                    ->label('Data de abertura')
                    ->columnSpan(2),

                DatePicker::make('tax_registration_status_date')
                    ->label('Data da situação cadastral')
                    ->columnSpan(2),

                TextInput::make('share_capital')
                    ->label('Capital social')
                    ->prefix('R$')
                    ->numeric()
                    ->step('0.01')
                    ->columnSpan(2),

                Toggle::make('is_head_office')
                    ->label('Matriz')
                    ->columnSpan(2),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativa',
                        'inactive' => 'Inativa',
                    ])
                    ->required()
                    ->default('active')
                    ->columnSpan(2),

                TextInput::make('company_size_name')
                    ->label('Porte')
                    ->maxLength(255)
                    ->columnSpan(2),

                Textarea::make('notes')
                    ->label('Observações')
                    ->columnSpanFull(),
            ]);
    }
}
