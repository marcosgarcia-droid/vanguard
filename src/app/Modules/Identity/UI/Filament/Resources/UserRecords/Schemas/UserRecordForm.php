<?php

namespace App\Modules\Identity\UI\Filament\Resources\UserRecords\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UserRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Section::make('Dados do usuário')
                    ->description('Informações básicas de acesso ao Vanguard.')
                    ->columns(6)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(3),

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpan(3),

                        TextInput::make('password')
                            ->label('Senha')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Preencha somente ao criar ou quando quiser alterar a senha.')
                            ->maxLength(255)
                            ->columnSpan(3),
                    ])
                    ->columnSpanFull(),

                Section::make('Acesso')
                    ->description('Funções e tenants vinculados ao usuário.')
                    ->columns(6)
                    ->schema([
                        Select::make('roles')
                            ->label('Funções')
                            ->relationship(
                                'roles',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('name', '!=', config('filament-shield.super_admin.name', 'super_admin')),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Role $record): string => self::roleLabel((string) $record->name))
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->disabled(fn (?User $record): bool => $record?->hasRole(config('filament-shield.super_admin.name', 'super_admin')) ?? false)
                            ->helperText('Super administrador é protegido e só pode ser atribuído por comando controlado. Use panel_user para liberar acesso básico ao painel.')
                            ->columnSpan(3),

                        Select::make('tenants')
                            ->label('Tenants')
                            ->relationship('tenants', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Vincule o usuário aos ambientes aos quais ele deve ter acesso.')
                            ->columnSpan(3),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function roleLabel(string $role): string
    {
        return match ($role) {
            'super_admin' => 'Super administrador',
            'panel_user' => 'Usuário do painel',
            'admin' => 'Administrador',
            'manager' => 'Gestor',
            'operator' => 'Operador',
            'viewer' => 'Visualizador',
            default => $role,
        };
    }
}
