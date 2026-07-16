<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Schemas;

use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionSource;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Support\VanguardText;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class AccessEventRecordInfolist
{
    public static function configure(
        Schema $schema
    ): Schema {
        return $schema
            ->columns(6)
            ->components([
                Tabs::make(
                    'Visualização do evento de acesso'
                )
                    ->id(
                        'access-event-record-infolist-tabs'
                    )
                    ->persistTab()
                    ->tabs([
                        self::eventTab(),
                        self::associationTab(),
                        self::decisionTab(),
                        self::executionAttemptsTab(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function eventTab(): Tab
    {
        return Tab::make('Evento')
            ->schema([
                Section::make('Evento recebido')
                    ->description(
                        'Dados técnicos recebidos do dispositivo e situação do processamento.'
                    )
                    ->columns(6)
                    ->schema([
                        TextEntry::make('occurred_at')
                            ->label('Data e hora do evento')
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('-')
                            ->columnSpan(2),

                        TextEntry::make('direction')
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
                            ->columnSpan(2),

                        TextEntry::make('status')
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
                            ->columnSpan(2),

                        TextEntry::make(
                            'event_type_display'
                        )
                            ->label('Tipo do evento')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): string => VanguardText::upper(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $record->event_type
                                            ?: '-'
                                    )
                                )
                            )
                            ->columnSpan(2),

                        TextEntry::make(
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
                            ->columnSpan(4),

                        TextEntry::make(
                            'external_event_id'
                        )
                            ->label('Identificador externo do evento')
                            ->placeholder('-')
                            ->columnSpan(3),

                        TextEntry::make('received_at')
                            ->label('Recebido em')
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('-')
                            ->columnSpan(3),

                        TextEntry::make('processed_at')
                            ->label('Processado em')
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('-')
                            ->columnSpan(2),

                        TextEntry::make(
                            'processing_attempts'
                        )
                            ->label('Tentativas de processamento')
                            ->placeholder('0')
                            ->columnSpan(2),

                        TextEntry::make('result_code')
                            ->label('Código do resultado')
                            ->placeholder('-')
                            ->columnSpan(2),

                        TextEntry::make('result_message')
                            ->label('Mensagem do processamento')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function associationTab(): Tab
    {
        return Tab::make('Associação técnica')
            ->schema([
                Section::make('Escopo e pessoa associada')
                    ->description(
                        'Vínculos técnicos encontrados durante o processamento do evento.'
                    )
                    ->columns(6)
                    ->schema([
                        TextEntry::make('tenant_display')
                            ->label('Grupo empresarial')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): string => self::tenantDisplay(
                                    $record
                                )
                            )
                            ->columnSpan(3),

                        TextEntry::make(
                            'organization_display'
                        )
                            ->label('Unidade')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): string => self::organizationDisplay(
                                    $record
                                )
                            )
                            ->columnSpan(3),

                        TextEntry::make(
                            'external_person_id'
                        )
                            ->label('Identificador externo da pessoa')
                            ->placeholder('Não informado')
                            ->columnSpan(3),

                        TextEntry::make('visitor_display')
                            ->label('Visitante associado')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): string => self::visitorDisplay(
                                    $record
                                )
                            )
                            ->columnSpan(3),
                    ])
                    ->columnSpanFull(),

                Section::make('Visita associada')
                    ->columns(6)
                    ->schema([
                        TextEntry::make(
                            'visit_status_display'
                        )
                            ->label('Status da visita')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): mixed => self::visitStatus(
                                    $record
                                )
                            )
                            ->badge()
                            ->formatStateUsing(
                                fn (
                                    mixed $state
                                ): string => self::visitStatusLabel(
                                    $state
                                )
                            )
                            ->color(
                                fn (
                                    mixed $state
                                ): string => self::visitStatusColor(
                                    $state
                                )
                            )
                            ->placeholder('Não associada')
                            ->columnSpan(2),

                        TextEntry::make(
                            'visit_purpose_display'
                        )
                            ->label('Finalidade')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): string => self::visitPurpose(
                                    $record
                                )
                            )
                            ->columnSpan(4),

                        TextEntry::make('host_display')
                            ->label('Anfitrião')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): string => self::hostDisplay(
                                    $record
                                )
                            )
                            ->columnSpan(3),

                        TextEntry::make(
                            'visit_period_display'
                        )
                            ->label('Período previsto')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): string => self::visitPeriod(
                                    $record
                                )
                            )
                            ->columnSpan(3),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function decisionTab(): Tab
    {
        return Tab::make('Decisão operacional')
            ->schema([
                Section::make('Última decisão registrada')
                    ->description(
                        'Decisão derivada do evento. A decisão não representa, por si só, uma liberação física.'
                    )
                    ->columns(6)
                    ->schema([
                        TextEntry::make(
                            'operational_decision'
                        )
                            ->label('Decisão')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): mixed => self::latestDecision(
                                    $record
                                )?->decision
                            )
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
                            ->placeholder('Não registrada')
                            ->columnSpan(3),

                        TextEntry::make(
                            'decision_version'
                        )
                            ->label('Versão')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): mixed => self::latestDecision(
                                    $record
                                )?->version
                            )
                            ->placeholder('-')
                            ->columnSpan(1),

                        TextEntry::make(
                            'decision_automatic_execution'
                        )
                            ->label('Execução automática habilitada')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): mixed => self::latestDecision(
                                    $record
                                )?->automatic_execution_enabled
                            )
                            ->badge()
                            ->formatStateUsing(
                                fn (
                                    mixed $state
                                ): string => self::booleanLabel(
                                    $state
                                )
                            )
                            ->color(
                                fn (
                                    mixed $state
                                ): string => $state === true
                                    ? 'warning'
                                    : 'gray'
                            )
                            ->placeholder('-')
                            ->columnSpan(2),

                        TextEntry::make(
                            'decision_decided_at'
                        )
                            ->label('Decidida em')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): mixed => self::latestDecision(
                                    $record
                                )?->decided_at
                            )
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('-')
                            ->columnSpan(2),

                        TextEntry::make(
                            'decision_reason_code'
                        )
                            ->label('Código do motivo')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): mixed => self::latestDecision(
                                    $record
                                )?->reason_code
                            )
                            ->placeholder('-')
                            ->columnSpan(4),

                        TextEntry::make(
                            'decision_reason_message'
                        )
                            ->label('Motivo')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): mixed => self::latestDecision(
                                    $record
                                )?->reason_message
                            )
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function executionAttemptsTab(): Tab
    {
        return Tab::make('Tentativas de execução')
            ->schema([
                Section::make('Tentativas operacionais')
                    ->description(
                        'Histórico auditável das tentativas derivadas das decisões operacionais.'
                    )
                    ->columns(6)
                    ->schema([
                        TextEntry::make(
                            'execution_attempts_summary'
                        )
                            ->label('Registros')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): string => self::executionAttemptsSummary(
                                    $record
                                )
                            )
                            ->columnSpanFull(),

                        RepeatableEntry::make(
                            'execution_attempts'
                        )
                            ->label('Tentativas registradas')
                            ->state(
                                fn (
                                    AccessEventRecord $record
                                ): array => self::executionAttemptItems(
                                    $record
                                )
                            )
                            ->columns(6)
                            ->schema([
                                TextEntry::make(
                                    'attempt_number'
                                )
                                    ->label('Tentativa')
                                    ->columnSpan(1),

                                TextEntry::make('status')
                                    ->label('Status')
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
                                    ->columnSpan(2),

                                TextEntry::make('source')
                                    ->label('Origem')
                                    ->badge()
                                    ->formatStateUsing(
                                        fn (
                                            mixed $state
                                        ): string => self::executionSourceLabel(
                                            $state
                                        )
                                    )
                                    ->columnSpan(1),

                                TextEntry::make('attempted_at')
                                    ->label('Iniciada em')
                                    ->dateTime('d/m/Y H:i:s')
                                    ->placeholder('-')
                                    ->columnSpan(2),

                                TextEntry::make('completed_at')
                                    ->label('Concluída em')
                                    ->dateTime('d/m/Y H:i:s')
                                    ->placeholder('-')
                                    ->columnSpan(2),

                                TextEntry::make(
                                    'automatic_execution_allowed'
                                )
                                    ->label('Automática permitida')
                                    ->badge()
                                    ->formatStateUsing(
                                        fn (
                                            mixed $state
                                        ): string => self::booleanLabel(
                                            $state
                                        )
                                    )
                                    ->columnSpan(2),

                                TextEntry::make('operator_name')
                                    ->label('Operador')
                                    ->placeholder(
                                        'Sem usuário humano'
                                    )
                                    ->columnSpan(2),

                                TextEntry::make(
                                    'visit_status_before'
                                )
                                    ->label('Status anterior da visita')
                                    ->formatStateUsing(
                                        fn (
                                            mixed $state
                                        ): string => self::visitStatusLabel(
                                            $state
                                        )
                                    )
                                    ->placeholder('-')
                                    ->columnSpan(3),

                                TextEntry::make(
                                    'visit_status_after'
                                )
                                    ->label('Status posterior da visita')
                                    ->formatStateUsing(
                                        fn (
                                            mixed $state
                                        ): string => self::visitStatusLabel(
                                            $state
                                        )
                                    )
                                    ->placeholder('-')
                                    ->columnSpan(3),

                                TextEntry::make('reason_code')
                                    ->label('Código do motivo')
                                    ->placeholder('-')
                                    ->columnSpan(2),

                                TextEntry::make('reason_message')
                                    ->label('Motivo')
                                    ->placeholder('-')
                                    ->columnSpan(4),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function deviceDisplay(
        AccessEventRecord $record
    ): string {
        $record->loadMissing('accessDevice');

        return VanguardText::upper(
            collect([
                $record->accessDevice?->code,
                $record->accessDevice?->name,
            ])
                ->filter()
                ->implode(' - ')
                ?: '-'
        );
    }

    private static function tenantDisplay(
        AccessEventRecord $record
    ): string {
        $record->loadMissing('tenant');

        return VanguardText::upper(
            $record->tenant?->name ?: '-'
        );
    }

    private static function organizationDisplay(
        AccessEventRecord $record
    ): string {
        $record->loadMissing('organization');

        return VanguardText::upper(
            $record->organization
                ?->operational_name
                ?: '-'
        );
    }

    private static function visitorDisplay(
        AccessEventRecord $record
    ): string {
        $record->loadMissing('visitor');

        return VanguardText::upper(
            $record->visitor?->display_name
                ?: 'NÃO ASSOCIADO'
        );
    }

    private static function visitStatus(
        AccessEventRecord $record
    ): mixed {
        $record->loadMissing('visit');

        return $record->visit?->status;
    }

    private static function visitPurpose(
        AccessEventRecord $record
    ): string {
        $record->loadMissing('visit');

        return $record->visit?->purpose ?: '-';
    }

    private static function hostDisplay(
        AccessEventRecord $record
    ): string {
        $record->loadMissing('visit.hostEmployee');

        return VanguardText::upper(
            $record->visit
                ?->hostEmployee
                ?->full_name
                ?: '-'
        );
    }

    private static function visitPeriod(
        AccessEventRecord $record
    ): string {
        $record->loadMissing('visit');

        if (! $record->visit) {
            return '-';
        }

        $start = $record->visit
            ->expected_start_at
            ?->format('d/m/Y H:i');

        $end = $record->visit
            ->expected_end_at
            ?->format('d/m/Y H:i');

        return collect([
            $start,
            $end,
        ])
            ->filter()
            ->implode(' até ')
            ?: '-';
    }

    private static function latestDecision(
        AccessEventRecord $record
    ): ?AccessEventOperationalDecisionRecord {
        $record->loadMissing(
            'latestOperationalDecision'
        );

        return $record->latestOperationalDecision;
    }

    private static function executionAttemptsSummary(
        AccessEventRecord $record
    ): string {
        $count = $record
            ->operationalExecutions()
            ->count();

        return match ($count) {
            0 => 'Nenhuma tentativa registrada.',
            1 => '1 tentativa registrada.',
            default => "{$count} tentativas registradas.",
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function executionAttemptItems(
        AccessEventRecord $record
    ): array {
        return $record
            ->operationalExecutions()
            ->with('operatorUser')
            ->orderByDesc('attempted_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(
                fn ($execution): array => [
                    'attempt_number' => $execution->attempt_number,
                    'status' => $execution->status,
                    'source' => $execution->source,
                    'attempted_at' => $execution->attempted_at,
                    'completed_at' => $execution->completed_at,
                    'automatic_execution_allowed' => $execution
                        ->automatic_execution_allowed,
                    'operator_name' => $execution->operatorUser?->name,
                    'visit_status_before' => $execution->visit_status_before,
                    'visit_status_after' => $execution->visit_status_after,
                    'reason_code' => $execution->reason_code,
                    'reason_message' => $execution->reason_message,
                ]
            )
            ->all();
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

    private static function executionSourceLabel(
        mixed $state
    ): string {
        $source =
            $state instanceof AccessEventOperationalExecutionSource
                ? $state
                : AccessEventOperationalExecutionSource::tryFrom(
                    (string) $state
                );

        return VanguardText::upper(
            $source?->label() ?: '-'
        );
    }

    private static function visitStatusLabel(
        mixed $state
    ): string {
        if (blank($state)) {
            return '-';
        }

        $status = $state instanceof VisitStatus
            ? $state
            : VisitStatus::tryFrom(
                (string) $state
            );

        return VanguardText::upper(
            $status?->label() ?: (string) $state
        );
    }

    private static function visitStatusColor(
        mixed $state
    ): string {
        $status = $state instanceof VisitStatus
            ? $state
            : VisitStatus::tryFrom(
                (string) $state
            );

        return match ($status) {
            VisitStatus::Authorized => 'success',
            VisitStatus::InProgress => 'info',
            VisitStatus::Completed => 'success',
            VisitStatus::PendingAuthorization => 'warning',
            VisitStatus::Rejected,
            VisitStatus::Cancelled,
            VisitStatus::Expired => 'danger',
            default => 'gray',
        };
    }

    private static function booleanLabel(
        mixed $state
    ): string {
        if ($state === null) {
            return '-';
        }

        return (bool) $state
            ? 'SIM'
            : 'NÃO';
    }
}
