<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Tables;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\AuthorizeHostVisitAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\AuthorizeVehicleEntryAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\AuthorizeVisitAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\CancelVisitAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\CheckInVisitAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\CheckOutVisitAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\RegisterVisitArrivalAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\RejectHostVisitAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\RejectVehicleEntryAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\RejectVisitAction;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions\RequestVehicleAuthorizationAction;
use App\Support\ActivityLog\VanguardActivityLogTimelineAction;
use App\Support\VanguardText;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VisitRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                app(TenantContext::class)->applyTenantScope(
                    $query->with([
                        'organization',
                        'visitor',
                        'hostEmployee',
                        'partner',
                        'vehicle.latestAuthorizationRequest',
                        'vehicle.pendingAuthorizationRequest',
                    ]),
                    auth()->user(),
                );

                app(TenantContext::class)->applyUserOrganizationScope(
                    $query,
                    auth()->user(),
                );

                return $query;
            })
            ->defaultSort(
                'expected_start_at',
                'desc'
            )
            ->columns([
                TextColumn::make('visitor.full_name')
                    ->label('Visitante')
                    ->state(
                        fn ($record): string => VanguardText::upper(
                            $record->visitor?->full_name
                        )
                    )
                    ->searchable()
                    ->sortable(),

                TextColumn::make('organization.display_name')
                    ->label('Unidade')
                    ->state(
                        fn ($record): string => VanguardText::upper(
                            $record->organization?->operational_name
                        )
                    )
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('hostEmployee.full_name')
                    ->label('Visitado')
                    ->state(
                        fn ($record): string => VanguardText::upper(
                            $record->hostEmployee?->full_name
                        )
                    )
                    ->placeholder('-'),

                TextColumn::make('purpose')
                    ->label('Finalidade')
                    ->formatStateUsing(
                        fn (mixed $state): string => VanguardText::upper(
                            (string) $state
                        )
                    )
                    ->searchable()
                    ->limit(50),

                TextColumn::make('expected_start_at')
                    ->label('Previsão')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(
                        fn (mixed $state): string => self::statusLabel($state)
                    )
                    ->color(
                        fn (mixed $state): string => self::statusColor($state)
                    )
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(
                        VisitStatus::operationalOptions()
                    ),
            ])
            ->recordActions([
                VanguardActivityLogTimelineAction::make(),

                ViewAction::make()
                    ->label('Visualizar')
                    ->tooltip('Visualizar')
                    ->iconButton()
                    ->modalHeading(
                        fn ($record): string => 'Visualizar visita - '
                            .($record->visitor?->full_name ?: 'VISITANTE')
                    )
                    ->modalWidth(Width::SevenExtraLarge),

                RegisterVisitArrivalAction::make(),
                RequestVehicleAuthorizationAction::make(),
                AuthorizeVehicleEntryAction::make(),
                RejectVehicleEntryAction::make(),
                AuthorizeHostVisitAction::make(),
                RejectHostVisitAction::make(),
                AuthorizeVisitAction::make(),
                RejectVisitAction::make(),
                CheckInVisitAction::make(),
                CheckOutVisitAction::make(),
                CancelVisitAction::make(),
            ]);
    }

    public static function applyTodayFilter(
        Builder $query
    ): Builder {
        return $query->whereDate(
            'expected_start_at',
            now()->toDateString()
        );
    }

    public static function applyStatusFilter(
        Builder $query,
        string $status
    ): Builder {
        $resolved = VisitStatus::tryFrom($status);

        if (! $resolved instanceof VisitStatus) {
            return $query;
        }

        return $query->where(
            'status',
            $resolved->value
        );
    }

    private static function statusLabel(mixed $status): string
    {
        $resolved = $status instanceof VisitStatus
            ? $status
            : VisitStatus::tryFrom((string) $status);

        return VanguardText::upper(
            $resolved?->label() ?: (string) $status
        );
    }

    private static function statusColor(mixed $status): string
    {
        $resolved = $status instanceof VisitStatus
            ? $status
            : VisitStatus::tryFrom((string) $status);

        return match ($resolved) {
            VisitStatus::Scheduled => 'info',
            VisitStatus::PendingAuthorization => 'warning',
            VisitStatus::Authorized => 'success',
            VisitStatus::Rejected => 'danger',
            VisitStatus::InProgress => 'primary',
            VisitStatus::Completed => 'success',
            VisitStatus::Cancelled,
            VisitStatus::Expired,
            VisitStatus::Draft => 'gray',
            default => 'gray',
        };
    }
}
