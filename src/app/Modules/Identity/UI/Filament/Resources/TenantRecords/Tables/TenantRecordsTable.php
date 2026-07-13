<?php

namespace App\Modules\Identity\UI\Filament\Resources\TenantRecords\Tables;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Support\ActivityLog\VanguardActivityLogTimelineAction;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['users', 'organizations']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Grupo empresarial')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('document')
                    ->label('Documento')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'Ativo',
                        'inactive' => 'Inativo',
                        default => $state ?: '-',
                    })
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Usuários')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('organizations_count')
                    ->label('Organizações')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('useTenant')
                    ->label('Usar grupo')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (TenantRecord $record): bool => app(TenantContext::class)->canSelectTenant(auth()->user(), $record))
                    ->action(function (TenantRecord $record): void {
                        app(TenantContext::class)->selectTenantForUser(auth()->user(), $record);

                        Notification::make()
                            ->title('Grupo ativo definido')
                            ->body('Agora você está operando no grupo '.$record->name.'.')
                            ->success()
                            ->send();
                    }),

                VanguardActivityLogTimelineAction::make(),

                ViewAction::make()
                    ->label('Visualizar')
                    ->tooltip('Visualizar')
                    ->iconButton()
                    ->modalHeading(fn (TenantRecord $record): string => 'Visualizar grupo empresarial - '.$record->name),

                EditAction::make()
                    ->label('Editar')
                    ->tooltip('Editar')
                    ->iconButton()
                    ->modalHeading(fn (TenantRecord $record): string => 'Editar grupo empresarial - '.$record->name)
                    ->modalSubmitActionLabel('Salvar alterações')
                    ->successNotificationTitle('Grupo empresarial atualizado'),
            ]);
    }
}
