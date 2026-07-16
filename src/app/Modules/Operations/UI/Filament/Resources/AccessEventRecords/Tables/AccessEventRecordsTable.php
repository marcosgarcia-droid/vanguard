<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Tables;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions\AccessEventActivityLogTimelineAction;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions\ReprocessAccessEventFlowAction;
use App\Support\VanguardText;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccessEventRecordsTable
{
    public static function configure(
        Table $table
    ): Table {
        return $table
            ->modifyQueryUsing(
                function (
                    Builder $query
                ): Builder {
                    app(TenantContext::class)
                        ->applyTenantScope(
                            $query->with([
                                'accessDevice',
                                'organization',
                                'visitor',
                                'visit',
                                'latestOperationalDecision',
                                'latestOperationalExecution',
                            ]),
                            auth()->user()
                        );

                    app(TenantContext::class)
                        ->applyUserOrganizationScope(
                            $query,
                            auth()->user()
                        );

                    return $query;
                }
            )
            ->defaultSort(
                'occurred_at',
                'desc'
            )
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Data e hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('direction')
                    ->label('Direção')
                    ->badge()
                    ->formatStateUsing(
                        fn (
                            mixed $state
                        ): string => self::directionLabel(
                            $state
                        )
                    )
                    ->color(
                        fn (
                            mixed $state
                        ): string => self::directionColor(
                            $state
                        )
                    )
                    ->sortable(),

                TextColumn::make(
                    'access_device_display'
                )
                    ->label('Dispositivo')
                    ->state(
                        fn (
                            AccessEventRecord $record
                        ): string => self::deviceDisplay(
                            $record
                        )
                    )
                    ->placeholder('-'),

                TextColumn::make(
                    'visitor.full_name'
                )
                    ->label('Pessoa associada')
                    ->formatStateUsing(
                        fn (
                            ?string $state
                        ): string => VanguardText::upper(
                            $state
                        )
                    )
                    ->placeholder('Não associada'),

                TextColumn::make(
                    'organization_display'
                )
                    ->label('Unidade')
                    ->state(
                        fn (
                            AccessEventRecord $record
                        ): string => VanguardText::upper(
                            $record->organization
                                ?->operational_name
                        )
                    )
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Processamento')
                    ->badge()
                    ->formatStateUsing(
                        fn (
                            mixed $state
                        ): string => self::eventStatusLabel(
                            $state
                        )
                    )
                    ->color(
                        fn (
                            mixed $state
                        ): string => self::eventStatusColor(
                            $state
                        )
                    )
                    ->sortable(),

                TextColumn::make(
                    'latestOperationalDecision.decision'
                )
                    ->label('Decisão')
                    ->badge()
                    ->formatStateUsing(
                        fn (
                            mixed $state
                        ): string => self::decisionLabel(
                            $state
                        )
                    )
                    ->color(
                        fn (
                            mixed $state
                        ): string => self::decisionColor(
                            $state
                        )
                    )
                    ->placeholder('Não avaliada'),

                TextColumn::make(
                    'latestOperationalExecution.status'
                )
                    ->label('Última tentativa')
                    ->badge()
                    ->formatStateUsing(
                        fn (
                            mixed $state
                        ): string => self::executionStatusLabel(
                            $state
                        )
                    )
                    ->color(
                        fn (
                            mixed $state
                        ): string => self::executionStatusColor(
                            $state
                        )
                    )
                    ->placeholder('Não registrada'),
            ])
            ->filters([
                SelectFilter::make(
                    'organization_id'
                )
                    ->label('Unidade')
                    ->options(
                        fn (): array => self::organizationOptions()
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('direction')
                    ->label('Direção')
                    ->options(
                        AccessEventDirection::options()
                    ),

                SelectFilter::make('status')
                    ->label('Processamento')
                    ->options(
                        AccessEventStatus::options()
                    ),
            ])
            ->emptyStateHeading(
                'Nenhum evento de acesso encontrado'
            )
            ->emptyStateDescription(
                'Os eventos recebidos dos dispositivos aparecerão nesta listagem.'
            )
            ->recordActions([
                AccessEventActivityLogTimelineAction::make(),

                ViewAction::make()
                    ->label('Visualizar')
                    ->tooltip('Visualizar')
                    ->iconButton()
                    ->modalHeading(
                        fn (
                            AccessEventRecord $record
                        ): string => 'Visualizar evento de acesso - '
                            .(
                                $record->occurred_at
                                    ?->format(
                                        'd/m/Y H:i:s'
                                    )
                                ?: $record
                                    ->external_event_id
                            )
                    )
                    ->modalWidth(
                        Width::SevenExtraLarge
                    ),

                ReprocessAccessEventFlowAction::make(),
            ]);
    }

    private static function deviceDisplay(
        AccessEventRecord $record
    ): string {
        return VanguardText::upper(
            collect([
                $record->accessDevice?->code,
                $record->accessDevice?->name,
            ])
                ->filter()
                ->implode(' - ')
        );
    }

    private static function directionLabel(
        mixed $state
    ): string {
        $direction = $state instanceof AccessEventDirection
            ? $state
            : AccessEventDirection::tryFrom(
                (string) $state
            );

        return VanguardText::upper(
            $direction?->label() ?: '-'
        );
    }

    private static function directionColor(
        mixed $state
    ): string {
        $direction = $state instanceof AccessEventDirection
            ? $state
            : AccessEventDirection::tryFrom(
                (string) $state
            );

        return match ($direction) {
            AccessEventDirection::Entry => 'success',
            AccessEventDirection::Exit => 'warning',
            default => 'gray',
        };
    }

    private static function eventStatusLabel(
        mixed $state
    ): string {
        $status = $state instanceof AccessEventStatus
            ? $state
            : AccessEventStatus::tryFrom(
                (string) $state
            );

        return VanguardText::upper(
            $status?->label() ?: '-'
        );
    }

    private static function eventStatusColor(
        mixed $state
    ): string {
        $status = $state instanceof AccessEventStatus
            ? $state
            : AccessEventStatus::tryFrom(
                (string) $state
            );

        return match ($status) {
            AccessEventStatus::Received => 'info',
            AccessEventStatus::PendingAssociation => 'warning',
            AccessEventStatus::Processed => 'success',
            AccessEventStatus::Ignored => 'gray',
            AccessEventStatus::Failed => 'danger',
            default => 'gray',
        };
    }

    private static function decisionLabel(
        mixed $state
    ): string {
        $decision =
            $state instanceof AccessEventOperationalDecision
                ? $state
                : AccessEventOperationalDecision::tryFrom(
                    (string) $state
                );

        return VanguardText::upper(
            $decision?->label() ?: '-'
        );
    }

    private static function decisionColor(
        mixed $state
    ): string {
        $decision =
            $state instanceof AccessEventOperationalDecision
                ? $state
                : AccessEventOperationalDecision::tryFrom(
                    (string) $state
                );

        return match ($decision) {
            AccessEventOperationalDecision::CheckInCandidate => 'success',
            AccessEventOperationalDecision::CheckOutCandidate => 'warning',
            AccessEventOperationalDecision::ManualReview => 'danger',
            AccessEventOperationalDecision::NoAction => 'gray',
            default => 'gray',
        };
    }

    private static function executionStatusLabel(
        mixed $state
    ): string {
        $status =
            $state instanceof AccessEventOperationalExecutionStatus
                ? $state
                : AccessEventOperationalExecutionStatus::tryFrom(
                    (string) $state
                );

        return VanguardText::upper(
            $status?->label() ?: '-'
        );
    }

    private static function executionStatusColor(
        mixed $state
    ): string {
        $status =
            $state instanceof AccessEventOperationalExecutionStatus
                ? $state
                : AccessEventOperationalExecutionStatus::tryFrom(
                    (string) $state
                );

        return match ($status) {
            AccessEventOperationalExecutionStatus::Pending => 'info',
            AccessEventOperationalExecutionStatus::Blocked => 'warning',
            AccessEventOperationalExecutionStatus::Executed => 'success',
            AccessEventOperationalExecutionStatus::Skipped => 'gray',
            AccessEventOperationalExecutionStatus::Failed => 'danger',
            default => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    private static function organizationOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $query = OrganizationRecord::query()
            ->where('status', 'active')
            ->orderBy('unit_code')
            ->orderBy('display_name');

        app(TenantContext::class)
            ->applyOrganizationScope(
                $query,
                $user
            );

        app(TenantContext::class)
            ->applyUserOrganizationScope(
                $query,
                $user,
                'id'
            );

        return $query
            ->get()
            ->mapWithKeys(
                fn (
                    OrganizationRecord $organization
                ): array => [
                    $organization->id => VanguardText::upper(
                        collect([
                            $organization->unit_code,
                            $organization->operational_name,
                        ])
                            ->filter()
                            ->implode(' - ')
                    ),
                ]
            )
            ->all();
    }
}
