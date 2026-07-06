<?php

namespace App\Modules\Identity\UI\Filament\Resources\TenantRecords\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TenantRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Section::make('Dados do tenant')
                    ->description('Identificação do ambiente, empresa ou grupo que reúne organizações e usuários.')
                    ->columns(6)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(3),

                        TextInput::make('legal_name')
                            ->label('Razão social')
                            ->maxLength(255)
                            ->columnSpan(3),

                        TextInput::make('document')
                            ->label('Documento')
                            ->maxLength(255)
                            ->helperText('Use CNPJ ou outro identificador administrativo do tenant.')
                            ->columnSpan(3),

                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->default('active')
                            ->options([
                                'active' => 'Ativo',
                                'inactive' => 'Inativo',
                            ])
                            ->native(false)
                            ->columnSpan(3),

                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
