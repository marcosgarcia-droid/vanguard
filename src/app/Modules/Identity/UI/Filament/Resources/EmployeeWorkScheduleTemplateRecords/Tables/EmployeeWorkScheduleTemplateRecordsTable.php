<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\Tables;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\Schemas\EmployeeWorkScheduleTemplateRecordForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeWorkScheduleTemplateRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => app(TenantContext::class)
                ->applyTenantScope($query->with('days'), auth()->user()))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Jornada')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('type_display')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('type', $direction)),

                TextColumn::make('weekly_workload_minutes')
                    ->label('Carga semanal')
                    ->formatStateUsing(fn (?int $state): string => self::minutesDisplay($state))
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('status_display')
                    ->label('Status')
                    ->badge()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('status', $direction)),

                IconColumn::make('is_system')
                    ->label('Padrão do sistema')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativa',
                        'inactive' => 'Inativa',
                    ]),

                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'standard' => 'Padrão',
                        'flexible' => 'Flexível',
                        'shift_12x36' => 'Escala 12x36',
                        'custom' => 'Personalizada',
                    ]),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Visualizar')
                    ->tooltip('Visualizar')
                    ->iconButton()
                    ->modalHeading(fn (EmployeeWorkScheduleTemplateRecord $record): string => 'Visualizar jornada - '.$record->name)
                    ->modalWidth(Width::SevenExtraLarge),

                EditAction::make()
                    ->label('Editar')
                    ->tooltip('Editar')
                    ->iconButton()
                    ->modalHeading(fn (EmployeeWorkScheduleTemplateRecord $record): string => 'Editar jornada - '.$record->name)
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalSubmitActionLabel('Salvar alterações')
                    ->mutateRecordDataUsing(fn (array $data, EmployeeWorkScheduleTemplateRecord $record): array => EmployeeWorkScheduleTemplateRecordForm::hydrateTransientFields($data, $record))
                    ->using(function (EmployeeWorkScheduleTemplateRecord $record, array $data): EmployeeWorkScheduleTemplateRecord {
                        $ruleGroups = $data['weekly_rule_groups'] ?? [];

                        $record->update(EmployeeWorkScheduleTemplateRecordForm::normalizeData($data));
                        EmployeeWorkScheduleTemplateRecordForm::syncGeneratedDays($record, $ruleGroups);

                        return $record;
                    })
                    ->successNotificationTitle('Jornada atualizada'),

                DeleteAction::make()
                    ->label('Excluir')
                    ->tooltip('Excluir')
                    ->iconButton()
                    ->visible(fn (EmployeeWorkScheduleTemplateRecord $record): bool => ! $record->is_system)
                    ->modalHeading('Excluir jornada')
                    ->modalDescription('A jornada será movida para a lixeira e poderá ser restaurada posteriormente.')
                    ->modalSubmitActionLabel('Excluir')
                    ->successNotificationTitle('Jornada excluída'),

                RestoreAction::make()
                    ->label('Restaurar')
                    ->tooltip('Restaurar')
                    ->iconButton()
                    ->modalHeading('Restaurar jornada')
                    ->modalDescription('A jornada voltará a aparecer normalmente na listagem.')
                    ->modalSubmitActionLabel('Restaurar')
                    ->successNotificationTitle('Jornada restaurada'),

                ForceDeleteAction::make()
                    ->label('Excluir definitivamente')
                    ->tooltip('Excluir definitivamente')
                    ->iconButton()
                    ->visible(fn (EmployeeWorkScheduleTemplateRecord $record): bool => ! $record->is_system)
                    ->modalHeading('Excluir jornada definitivamente')
                    ->modalDescription('Esta ação não poderá ser desfeita.')
                    ->modalSubmitActionLabel('Excluir definitivamente')
                    ->successNotificationTitle('Jornada excluída definitivamente'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    private static function minutesDisplay(?int $minutes): string
    {
        if (! $minutes) {
            return '-';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%dh%02d', $hours, $remainingMinutes);
    }
}
