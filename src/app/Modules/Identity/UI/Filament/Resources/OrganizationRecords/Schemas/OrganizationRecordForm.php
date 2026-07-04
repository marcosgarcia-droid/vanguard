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

                TextInput::make('display_name')
                    ->label('Nome da unidade')
                    ->helperText('Nome usado pelo time no dia a dia. Ex: AGRONORTE TOCANTINÓPOLIS.')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(3),

                TextInput::make('unit_code')
                    ->label('Código da unidade')
                    ->helperText('Use um código curto para diferenciar filiais. Ex: TOC-01, TC-OFICINA-01.')
                    ->maxLength(255)
                    ->columnSpan(3),

                TextInput::make('cnpj')
                    ->label('CNPJ')
                    ->helperText('Após salvar, o CNPJ só poderá ser alterado por uma ação específica de correção.')
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                        ? preg_replace('/\D+/', '', $state)
                        : null)
                    ->maxLength(18)
                    ->disabledOn('edit')
                    ->columnSpan(3),

                TextInput::make('tax_registration_status_name')
                    ->label('Situação cadastral')
                    ->helperText('Atualizado pela consulta CNPJ.')
                    ->maxLength(255)
                    ->disabledOn('edit')
                    ->columnSpan(3),

                TextInput::make('legal_name')
                    ->label('Razão social')
                    ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                    ->required()
                    ->maxLength(255)
                    ->disabledOn('edit')
                    ->columnSpan(3),

                TextInput::make('trade_name')
                    ->label('Nome fantasia')
                    ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                    ->maxLength(255)
                    ->disabledOn('edit')
                    ->columnSpan(3),

                TextInput::make('establishment_type')
                    ->label('Tipo de estabelecimento')
                    ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                    ->maxLength(255)
                    ->disabledOn('edit')
                    ->columnSpan(3),

                TextInput::make('legal_nature_name')
                    ->label('Natureza jurídica')
                    ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                    ->maxLength(255)
                    ->disabledOn('edit')
                    ->columnSpan(3),

                DatePicker::make('opened_at')
                    ->label('Data de abertura')
                    ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                    ->disabledOn('edit')
                    ->columnSpan(2),

                DatePicker::make('tax_registration_status_date')
                    ->label('Data da situação cadastral')
                    ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                    ->disabledOn('edit')
                    ->columnSpan(2),

                TextInput::make('share_capital')
                    ->label('Capital social')
                    ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                    ->prefix('R$')
                    ->numeric()
                    ->step('0.01')
                    ->disabledOn('edit')
                    ->columnSpan(2),

                Toggle::make('is_head_office')
                    ->label('Matriz')
                    ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                    ->disabledOn('edit')
                    ->columnSpan(2),

                Select::make('status')
                    ->label('Status interno')
                    ->helperText('Controle operacional do Vanguard. Não altera a situação cadastral na Receita.')
                    ->options([
                        'active' => 'Ativa',
                        'inactive' => 'Inativa',
                    ])
                    ->required()
                    ->default('active')
                    ->columnSpan(2),

                TextInput::make('company_size_name')
                    ->label('Porte')
                    ->helperText('Dado cadastral atualizado pela consulta CNPJ.')
                    ->maxLength(255)
                    ->disabledOn('edit')
                    ->columnSpan(2),

                Textarea::make('notes')
                    ->label('Observações')
                    ->columnSpanFull(),
            ]);
    }
}
