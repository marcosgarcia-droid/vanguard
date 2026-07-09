<?php

namespace App\Modules\Identity\UI\Filament\Resources\UserRecords\Tables;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['roles', 'tenants']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label('Funções')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::roleLabel($state))
                    ->placeholder('-'),

                TextColumn::make('tenants.name')
                    ->label('Grupos empresariais')
                    ->badge()
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->tooltip('Editar')
                    ->iconButton()
                    ->modalHeading(fn (User $record): string => 'Editar usuário - '.$record->name)
                    ->modalSubmitActionLabel('Salvar alterações')
                    ->successNotificationTitle('Usuário atualizado'),

                DeleteAction::make()
                    ->label('Excluir')
                    ->tooltip('Excluir')
                    ->iconButton()
                    ->hidden(fn (User $record): bool => auth()->id() === $record->id)
                    ->modalHeading('Excluir usuário')
                    ->modalDescription('Esta ação removerá o usuário do sistema. O próprio usuário logado não pode ser excluído por esta ação.')
                    ->modalSubmitActionLabel('Excluir')
                    ->successNotificationTitle('Usuário excluído'),
            ]);
    }

    private static function roleLabel(?string $role): string
    {
        return match ($role) {
            'super_admin' => 'Super administrador',
            'panel_user' => 'Usuário do painel',
            'admin' => 'Administrador',
            'manager' => 'Gestor',
            'operator' => 'Operador',
            'viewer' => 'Visualizador',
            default => $role ?: '-',
        };
    }
}
