<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Tables;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions\AccessEventActivityLogTimelineAction;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions\ContinueManuallyAssociatedAccessEventFlowAction;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions\ManualAssociateAccessEventAction;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions\RecordAccessEventManualReviewAction;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions\ReprocessAccessEventFlowAction;
use App\Support\VanguardText;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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
                                'latestManualReview',
                                'latestManualReview.reprocessConsumption',
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
            ->poll(
                self::pollingInterval()
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
                    ->searchable(
                        query: fn (
                            Builder $query,
                            string $search
                        ): Builder => self::applyOperationalSearch(
                            $query,
                            $search
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

                TextColumn::make(
                    'operational_status'
                )
                    ->label('Situação operacional')
                    ->badge()
                    ->state(
                        fn (
                            AccessEventRecord $record
                        ): string => AccessEventOperationalStatus::summary(
                            $record
                        )['label']
                    )
                    ->color(
                        fn (
                            AccessEventRecord $record
                        ): string => AccessEventOperationalStatus::summary(
                            $record
                        )['color']
                    )
                    ->tooltip(
                        fn (
                            AccessEventRecord $record
                        ): string => AccessEventOperationalStatus::summary(
                            $record
                        )['description']
                    )
                    ->wrap(),

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

                Filter::make('occurred_at_period')
                    ->label('Período do evento')
                    ->schema([
                        DatePicker::make('from')
                            ->label('De')
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('until')
                            ->label('Até')
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(
                        fn (
                            Builder $query,
                            array $data
                        ): Builder => self::applyOccurredAtPeriod(
                            $query,
                            $data
                        )
                    )
                    ->indicateUsing(
                        fn (
                            array $data
                        ): array => self::periodIndicators(
                            $data
                        )
                    ),

                SelectFilter::make(
                    'latest_operational_decision'
                )
                    ->label('Decisão operacional')
                    ->options(
                        AccessEventOperationalDecision::options()
                    )
                    ->query(
                        fn (
                            Builder $query,
                            array $data
                        ): Builder => self::applyLatestDecisionFilter(
                            $query,
                            $data['value'] ?? null
                        )
                    ),

                SelectFilter::make(
                    'latest_operational_execution_status'
                )
                    ->label('Última tentativa')
                    ->options(
                        AccessEventOperationalExecutionStatus::options()
                    )
                    ->query(
                        fn (
                            Builder $query,
                            array $data
                        ): Builder => self::applyLatestExecutionStatusFilter(
                            $query,
                            $data['value'] ?? null
                        )
                    ),

                TernaryFilter::make('visitor_id')
                    ->label('Pessoa associada')
                    ->placeholder('Todas')
                    ->trueLabel(
                        'Com pessoa associada'
                    )
                    ->falseLabel(
                        'Sem pessoa associada'
                    )
                    ->nullable(),

                TernaryFilter::make('visit_id')
                    ->label('Visita associada')
                    ->placeholder('Todas')
                    ->trueLabel(
                        'Com visita associada'
                    )
                    ->falseLabel(
                        'Sem visita associada'
                    )
                    ->nullable(),
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

                ManualAssociateAccessEventAction::make(),

                ContinueManuallyAssociatedAccessEventFlowAction::make(),

                RecordAccessEventManualReviewAction::make(),

                ReprocessAccessEventFlowAction::make(),
            ]);
    }

    public static function pollingInterval(): ?string
    {
        if (
            ! (bool) config(
                'access_control.event_list_polling_enabled',
                false
            )
        ) {
            return null;
        }

        $seconds = (int) config(
            'access_control.event_list_polling_interval_seconds',
            30
        );

        /*
         * Evita polling excessivamente frequente e também impede
         * intervalos muito longos configurados por engano.
         */
        $seconds = max(
            30,
            min(
                300,
                $seconds
            )
        );

        return "{$seconds}s";
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

    public static function applyOperationalSearch(
        Builder $query,
        string $search
    ): Builder {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $like = "%{$search}%";

        return $query->where(
            function (
                Builder $searchQuery
            ) use (
                $like
            ): void {
                $searchQuery
                    ->where(
                        'external_event_id',
                        'like',
                        $like
                    )
                    ->orWhere(
                        'external_person_id',
                        'like',
                        $like
                    )
                    ->orWhereHas(
                        'accessDevice',
                        function (
                            Builder $deviceQuery
                        ) use (
                            $like
                        ): void {
                            $deviceQuery
                                ->where(
                                    'code',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'name',
                                    'like',
                                    $like
                                );
                        }
                    )
                    ->orWhereHas(
                        'visitor',
                        function (
                            Builder $visitorQuery
                        ) use (
                            $like
                        ): void {
                            $visitorQuery->where(
                                'full_name',
                                'like',
                                $like
                            );
                        }
                    );
            }
        );
    }

    /**
     * @param array{
     *     from?: mixed,
     *     until?: mixed
     * } $data
     */
    public static function applyOccurredAtPeriod(
        Builder $query,
        array $data
    ): Builder {
        $from = $data['from'] ?? null;
        $until = $data['until'] ?? null;

        return $query
            ->when(
                filled($from),
                fn (
                    Builder $periodQuery
                ): Builder => $periodQuery->where(
                    'occurred_at',
                    '>=',
                    Carbon::parse(
                        (string) $from
                    )->startOfDay()
                )
            )
            ->when(
                filled($until),
                fn (
                    Builder $periodQuery
                ): Builder => $periodQuery->where(
                    'occurred_at',
                    '<=',
                    Carbon::parse(
                        (string) $until
                    )->endOfDay()
                )
            );
    }

    public static function applyEventStatusFilter(
        Builder $query,
        mixed $status
    ): Builder {
        $status = is_string($status)
            ? AccessEventStatus::tryFrom(
                $status
            )
            : null;

        if ($status === null) {
            return $query;
        }

        return $query->where(
            'status',
            $status->value
        );
    }

    public static function applyOpenManualReviewFilter(
        Builder $query
    ): Builder {
        return self::applyLatestDecisionFilter(
            $query,
            AccessEventOperationalDecision::ManualReview
                ->value
        )
            ->whereNotExists(
                function (
                    $reviewQuery
                ): void {
                    $reviewQuery
                        ->selectRaw('1')
                        ->from(
                            'access_event_manual_reviews as resolved_review'
                        )
                        ->whereColumn(
                            'resolved_review.access_event_id',
                            'access_events.id'
                        )
                        ->where(
                            'resolved_review.disposition',
                            AccessEventManualReviewDisposition::ResolvedWithoutOperation
                                ->value
                        )
                        ->whereRaw(
                            'resolved_review.id = (
                                select latest_review.id
                                from access_event_manual_reviews as latest_review
                                where latest_review.access_event_id =
                                    resolved_review.access_event_id
                                order by
                                    latest_review.reviewed_at desc,
                                    latest_review.created_at desc
                                limit 1
                            )'
                        );
                }
            );
    }

    public static function applyLatestDecisionFilter(
        Builder $query,
        mixed $decision
    ): Builder {
        $decision = is_string($decision)
            ? AccessEventOperationalDecision::tryFrom(
                $decision
            )
            : null;

        if ($decision === null) {
            return $query;
        }

        return $query->whereExists(
            function (
                $decisionQuery
            ) use (
                $decision
            ): void {
                $decisionQuery
                    ->selectRaw('1')
                    ->from(
                        'access_event_operational_decisions as filtered_decision'
                    )
                    ->whereColumn(
                        'filtered_decision.access_event_id',
                        'access_events.id'
                    )
                    ->where(
                        'filtered_decision.decision',
                        $decision->value
                    )
                    ->whereRaw(
                        'filtered_decision.version = (
                            select max(latest_decision.version)
                            from access_event_operational_decisions as latest_decision
                            where latest_decision.access_event_id =
                                filtered_decision.access_event_id
                        )'
                    );
            }
        );
    }

    public static function applyLatestExecutionStatusFilter(
        Builder $query,
        mixed $status
    ): Builder {
        $status = is_string($status)
            ? AccessEventOperationalExecutionStatus::tryFrom(
                $status
            )
            : null;

        if ($status === null) {
            return $query;
        }

        return $query->whereExists(
            function (
                $executionQuery
            ) use (
                $status
            ): void {
                $executionQuery
                    ->selectRaw('1')
                    ->from(
                        'access_event_operational_executions as filtered_execution'
                    )
                    ->whereColumn(
                        'filtered_execution.access_event_id',
                        'access_events.id'
                    )
                    ->where(
                        'filtered_execution.status',
                        $status->value
                    )
                    ->whereRaw(
                        'filtered_execution.id = (
                            select latest_execution.id
                            from access_event_operational_executions as latest_execution
                            where latest_execution.access_event_id =
                                filtered_execution.access_event_id
                            order by
                                latest_execution.attempted_at desc,
                                latest_execution.created_at desc
                            limit 1
                        )'
                    );
            }
        );
    }

    /**
     * @param array{
     *     from?: mixed,
     *     until?: mixed
     * } $data
     * @return array<int, string>
     */
    private static function periodIndicators(
        array $data
    ): array {
        $indicators = [];

        if (filled($data['from'] ?? null)) {
            $indicators[] = 'Desde '
                .Carbon::parse(
                    (string) $data['from']
                )->format('d/m/Y');
        }

        if (filled($data['until'] ?? null)) {
            $indicators[] = 'Até '
                .Carbon::parse(
                    (string) $data['until']
                )->format('d/m/Y');
        }

        return $indicators;
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
