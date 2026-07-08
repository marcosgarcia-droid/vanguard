<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Tables;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Support\VanguardText;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => app(TenantContext::class)
                ->applyTenantScope(
                    $query->with(['tenant', 'organization', 'user', 'manager', 'documents', 'contacts', 'addresses', 'workSchedules.template']),
                    auth()->user(),
                ))
            ->defaultSort('full_name')
            ->columns([
                TextColumn::make('employee_code')
                    ->label('Matrícula')
                    ->formatStateUsing(fn (?string $state): string => VanguardText::upper($state))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('full_name')
                    ->label('Funcionário')
                    ->formatStateUsing(fn (?string $state): string => VanguardText::upper($state))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('organization.display_name')
                    ->label('Unidade')
                    ->formatStateUsing(fn (?string $state, EmployeeRecord $record): string => VanguardText::upper($record->organization?->operational_name))
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('position')
                    ->label('Cargo')
                    ->formatStateUsing(fn (?string $state): string => VanguardText::upper($state))
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('mobile_phone')
                    ->label('Celular')
                    ->formatStateUsing(fn (?string $state): string => self::formatPhone($state))
                    ->placeholder('-'),

                TextColumn::make('employment_type')
                    ->label('Vínculo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::employmentTypeLabel($state)),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Visualizar')
                    ->tooltip('Visualizar')
                    ->iconButton()
                    ->modalHeading(fn (EmployeeRecord $record): string => 'Visualizar funcionário - '.$record->display_name)
                    ->modalWidth(Width::SevenExtraLarge),

                EditAction::make()
                    ->label('Editar')
                    ->tooltip('Editar')
                    ->iconButton()
                    ->modalHeading(fn (EmployeeRecord $record): string => 'Editar funcionário - '.$record->display_name)
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalSubmitActionLabel('Salvar alterações')
                    ->successNotificationTitle('Funcionário atualizado'),

                DeleteAction::make()
                    ->label('Excluir')
                    ->tooltip('Excluir')
                    ->iconButton()
                    ->modalHeading('Excluir funcionário')
                    ->modalDescription('O funcionário será movido para a lixeira e poderá ser restaurado posteriormente.')
                    ->modalSubmitActionLabel('Excluir')
                    ->successNotificationTitle('Funcionário excluído'),

                RestoreAction::make()
                    ->label('Restaurar')
                    ->tooltip('Restaurar')
                    ->iconButton()
                    ->modalHeading('Restaurar funcionário')
                    ->modalSubmitActionLabel('Restaurar')
                    ->successNotificationTitle('Funcionário restaurado'),

                ForceDeleteAction::make()
                    ->label('Excluir definitivamente')
                    ->tooltip('Excluir definitivamente')
                    ->iconButton()
                    ->modalHeading('Excluir funcionário definitivamente')
                    ->modalDescription('Esta ação não poderá ser desfeita.')
                    ->modalSubmitActionLabel('Excluir definitivamente')
                    ->successNotificationTitle('Funcionário excluído definitivamente'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    private static function statusLabel(?string $status): string
    {
        return match ($status) {
            'active' => 'ATIVO',
            'inactive' => 'INATIVO',
            'terminated' => 'DESLIGADO',
            default => $status ?: '-',
        };
    }

    private static function employmentTypeLabel(?string $type): string
    {
        return match ($type) {
            'employee' => 'FUNCIONÁRIO',
            'contractor' => 'PRESTADOR',
            'intern' => 'ESTAGIÁRIO',
            'temporary' => 'TEMPORÁRIO',
            default => $type ?: '-',
        };
    }

    private static function formatPhone(?string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);

        if (strlen($phone) === 11) {
            return '('.substr($phone, 0, 2).') '.substr($phone, 2, 5).'-'.substr($phone, 7);
        }

        if (strlen($phone) === 10) {
            return '('.substr($phone, 0, 2).') '.substr($phone, 2, 4).'-'.substr($phone, 6);
        }

        return $phone ?: '-';
    }
}
