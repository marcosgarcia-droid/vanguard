<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Tables;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Actions\CreateEmployeeFacialCredentialSynchronizationAction;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Actions\EditEmployeeRecordAction;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Actions\ReprocessEmployeeFacialPhotoDerivativeAction;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Actions\UpdateEmployeeFacialPhotoAction;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Schemas\EmployeeFacialCredentialSynchronizationPresentation;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Schemas\EmployeeFacialPhotoDerivativePresentation;
use App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Schemas\EmployeeFacialPhotoStatusPresentation;
use App\Support\ActivityLog\VanguardActivityLogTimelineAction;
use App\Support\VanguardText;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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
            ->modifyQueryUsing(function (Builder $query): Builder {
                app(TenantContext::class)->applyTenantScope(
                    $query->with([
                        'tenant',
                        'organization',
                        'user',
                        'manager',
                        'documents',
                        'contacts',
                        'addresses',
                        'workSchedules.template',
                        'latestFacialPhoto.latestValidationAttempt',
                        'latestFacialPhoto.derivatives.latestAttempt',
                        'facialCredentialSynchronizations.accessDevice',
                        'facialCredentialSynchronizations.latestAttempt',
                    ]),
                    auth()->user(),
                );

                app(TenantContext::class)->applyUserOrganizationScope($query, auth()->user());

                return $query;
            })
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

                TextColumn::make('facial_photo_status')
                    ->label('Foto facial')
                    ->badge()
                    ->state(
                        fn (
                            EmployeeRecord $record
                        ): string => EmployeeFacialPhotoStatusPresentation::summary(
                            $record
                        )['label']
                    )
                    ->color(
                        fn (
                            EmployeeRecord $record
                        ): string => EmployeeFacialPhotoStatusPresentation::summary(
                            $record
                        )['color']
                    )
                    ->toggleable(),

                TextColumn::make('facial_photo_derivative_status')
                    ->label('Preparação facial')
                    ->badge()
                    ->state(
                        fn (
                            EmployeeRecord $record
                        ): string => EmployeeFacialPhotoDerivativePresentation::summary(
                            $record
                        )['label']
                    )
                    ->color(
                        fn (
                            EmployeeRecord $record
                        ): string => EmployeeFacialPhotoDerivativePresentation::summary(
                            $record
                        )['color']
                    )
                    ->toggleable(),

                TextColumn::make('facial_credential_synchronization_status')
                    ->label('Sincronização facial')
                    ->badge()
                    ->state(
                        fn (
                            EmployeeRecord $record
                        ): string => EmployeeFacialCredentialSynchronizationPresentation::summary(
                            $record
                        )['label']
                    )
                    ->color(
                        fn (
                            EmployeeRecord $record
                        ): string => EmployeeFacialCredentialSynchronizationPresentation::summary(
                            $record
                        )['color']
                    )
                    ->toggleable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                VanguardActivityLogTimelineAction::make(),

                ReprocessEmployeeFacialPhotoDerivativeAction::make(),

                CreateEmployeeFacialCredentialSynchronizationAction::make(),

                UpdateEmployeeFacialPhotoAction::make(),

                ViewAction::make()
                    ->label('Visualizar')
                    ->tooltip('Visualizar')
                    ->iconButton()
                    ->modalHeading(fn (EmployeeRecord $record): string => 'Visualizar funcionário - '.$record->display_name)
                    ->modalWidth(Width::SevenExtraLarge),

                EditEmployeeRecordAction::make(),

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
