<?php

namespace App\Modules\Identity\UI\Filament\Resources\ActivityLogs;

use AlizHarb\ActivityLog\Resources\ActivityLogs\ActivityLogResource;
use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Identity\UI\Filament\Resources\ActivityLogs\Pages\ListVanguardActivityLogs;
use App\Modules\Identity\UI\Filament\Resources\ActivityLogs\Pages\ViewVanguardActivityLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class VanguardActivityLogResource extends ActivityLogResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListVanguardActivityLogs::route('/'),
            'view' => ViewVanguardActivityLog::route('/{record}'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data/hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('event')
                    ->label('Evento')
                    ->formatStateUsing(fn (?string $state): string => self::eventLabel($state))
                    ->badge()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('subject_type')
                    ->label('Registro')
                    ->formatStateUsing(fn (?string $state, Activity $record): string => self::recordLabel(
                        $record->subject_type,
                        $record->subject_id,
                    ))
                    ->searchable(),

                TextColumn::make('causer.name')
                    ->label('Usuário')
                    ->formatStateUsing(fn (?string $state, Activity $record): string => $state ?: self::recordLabel(
                        $record->causer_type,
                        $record->causer_id,
                    ))
                    ->description(fn (Activity $record): ?string => data_get($record->causer, 'email'))
                    ->searchable(),
            ])
            ->filters([])
            ->recordUrl(null)
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return self::currentUserIsSuperAdmin();
    }

    public static function canView(Model $record): bool
    {
        return self::currentUserIsSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    private static function currentUserIsSuperAdmin(): bool
    {
        return auth()->user()?->hasRole(config('filament-shield.super_admin.name', 'super_admin')) ?? false;
    }

    private static function eventLabel(?string $event): string
    {
        return match ($event) {
            'created' => 'Criado',
            'updated' => 'Atualizado',
            'deleted' => 'Excluído',
            'restored' => 'Restaurado',
            default => $event ? Str::headline($event) : '-',
        };
    }

    private static function recordLabel(?string $type, mixed $id): string
    {
        $label = match ($type) {
            User::class => 'Usuário',
            TenantRecord::class => 'Grupo empresarial',
            OrganizationRecord::class => 'Organização',
            EmployeeRecord::class => 'Funcionário',
            PartnerRecord::class => 'Parceiro',
            ClassificationOptionRecord::class => 'Classificação',
            EmployeeWorkScheduleTemplateRecord::class => 'Jornada de trabalho',
            default => $type ? class_basename($type) : 'Sistema',
        };

        if (blank($id)) {
            return $label;
        }

        return "{$label} #".Str::limit((string) $id, 8, '');
    }
}
