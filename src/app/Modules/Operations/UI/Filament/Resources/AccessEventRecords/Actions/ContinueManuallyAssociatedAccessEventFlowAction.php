<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation\ContinueManuallyAssociatedAccessEventFlowCommand;
use App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation\ContinueManuallyAssociatedAccessEventFlowException;
use App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation\ContinueManuallyAssociatedAccessEventFlowResult;
use App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation\ContinueManuallyAssociatedAccessEventFlowUseCase;
use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowResult;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class ContinueManuallyAssociatedAccessEventFlowAction
{
    public static function make(): Action
    {
        return Action::make(
            'continueManuallyAssociatedAccessEventFlow'
        )
            ->label('Continuar fluxo')
            ->tooltip('Continuar fluxo')
            ->icon('heroicon-o-play-circle')
            ->iconButton()
            ->color('warning')
            ->requiresConfirmation()
            ->closeModalByClickingAway(false)
            ->modalHeading(
                'Continuar fluxo após associação manual'
            )
            ->modalDescription(
                'O VANGUARD continuará o processamento operacional deste evento associado manualmente. Em modo observador, nenhuma entrada ou saída será registrada. Em modo primário, com as operações automáticas habilitadas, a visita poderá ter a entrada ou saída registrada. Nenhum comando físico será enviado ao leitor.'
            )
            ->modalSubmitActionLabel(
                'Continuar fluxo'
            )
            ->visible(
                fn (
                    AccessEventRecord $record
                ): bool => self::isEligibleRecord(
                    $record
                )
                    && (
                        auth()->user()?->can(
                            'reprocessFlow',
                            $record
                        ) ?? false
                    )
            )
            ->action(
                function (
                    AccessEventRecord $record
                ): void {
                    $user = auth()->user();

                    if (! $user instanceof User) {
                        Notification::make()
                            ->title(
                                'Não foi possível identificar o operador'
                            )
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Gate::authorize(
                        'reprocessFlow',
                        $record
                    );

                    try {
                        $result = app(
                            ContinueManuallyAssociatedAccessEventFlowUseCase::class
                        )->execute(
                            new ContinueManuallyAssociatedAccessEventFlowCommand(
                                eventId: $record->id,
                                operatorUserId: (int) $user->id,
                            )
                        );

                        self::auditSuccess(
                            $record,
                            $user,
                            $result
                        );

                        self::sendResultNotification(
                            $result
                        );
                    } catch (
                        ContinueManuallyAssociatedAccessEventFlowException $exception
                    ) {
                        self::auditFailure(
                            $record,
                            $user,
                            $exception
                        );

                        Notification::make()
                            ->title(
                                'Não foi possível continuar o fluxo'
                            )
                            ->body(
                                $exception->getMessage()
                            )
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }
            );
    }

    public static function isEligibleRecord(
        AccessEventRecord $record
    ): bool {
        $status = $record->status;

        if (! $status instanceof AccessEventStatus) {
            $status = AccessEventStatus::tryFrom(
                (string) $status
            );
        }

        if (
            $status !== AccessEventStatus::Processed
            || trim((string) $record->result_code)
                !== 'manual_association_completed'
            || blank($record->visitor_id)
            || blank($record->visit_id)
        ) {
            return false;
        }

        $decisionCount = array_key_exists(
            'operational_decisions_count',
            $record->getAttributes()
        )
            ? (int) $record->getAttribute(
                'operational_decisions_count'
            )
            : $record
                ->operationalDecisions()
                ->count();

        $executionCount = array_key_exists(
            'operational_executions_count',
            $record->getAttributes()
        )
            ? (int) $record->getAttribute(
                'operational_executions_count'
            )
            : $record
                ->operationalExecutions()
                ->count();

        return $decisionCount === 0
            && $executionCount === 0;
    }

    private static function sendResultNotification(
        ContinueManuallyAssociatedAccessEventFlowResult $result
    ): void {
        $flow = $result->flow;

        $allDuplicates =
            self::allResultsAreDuplicates(
                $flow
            );

        $notification = Notification::make()
            ->title(
                self::notificationTitle(
                    $flow,
                    $allDuplicates
                )
            )
            ->body(
                self::resultMessage($flow)
            );

        if (
            $flow->registration->status
            === AccessEventOperationalExecutionStatus::Failed
        ) {
            $notification->danger();
        } elseif (
            in_array(
                $flow->registration->status,
                [
                    AccessEventOperationalExecutionStatus::Blocked,
                    AccessEventOperationalExecutionStatus::Skipped,
                ],
                true
            )
            && ! $allDuplicates
        ) {
            $notification->warning();
        } else {
            $notification->success();
        }

        $notification->send();
    }

    private static function notificationTitle(
        ContinueAccessEventFlowResult $flow,
        bool $allDuplicates
    ): string {
        if ($allDuplicates) {
            return 'Fluxo continuado sem novas alterações';
        }

        if (
            $flow->execution?->status
            === AccessEventOperationalExecutionStatus::Executed
        ) {
            return 'Fluxo continuado e operação concluída';
        }

        return match (
            $flow->registration->status
        ) {
            AccessEventOperationalExecutionStatus::Blocked => 'Fluxo continuado com execução bloqueada',

            AccessEventOperationalExecutionStatus::Skipped => 'Fluxo continuado sem operação executável',

            AccessEventOperationalExecutionStatus::Failed => 'Fluxo continuado com falha',

            default => 'Fluxo continuado',
        };
    }

    private static function resultMessage(
        ContinueAccessEventFlowResult $flow
    ): string {
        return collect([
            'Processamento: '
                .$flow->processing
                    ->status
                    ->label()
                .'.',

            'Decisão: '
                .$flow->decision
                    ->decision
                    ->label()
                .'.',

            'Tentativa: '
                .$flow->registration
                    ->status
                    ->label()
                .'.',

            'Motivo: '
                .self::reasonLabel(
                    $flow->registration
                        ->reasonCode
                )
                .'.',
        ])->implode(' ');
    }

    private static function reasonLabel(
        string $reasonCode
    ): string {
        return match ($reasonCode) {
            'automatic_execution_disabled' => 'execução automática desabilitada',

            'decision_not_executable' => 'a decisão não exige execução',

            'automatic_execution_registered' => 'execução automática registrada',

            'automatic_check_in_executed' => 'entrada registrada automaticamente',

            'automatic_check_out_executed' => 'saída registrada automaticamente',

            default => Str::of($reasonCode)
                ->replace('_', ' ')
                ->lower()
                ->toString(),
        };
    }

    private static function allResultsAreDuplicates(
        ContinueAccessEventFlowResult $flow
    ): bool {
        return $flow->processing->duplicate
            && $flow->decision->duplicate
            && $flow->registration->duplicate
            && (
                $flow->execution === null
                || $flow->execution->duplicate
            );
    }

    private static function auditSuccess(
        AccessEventRecord $record,
        User $user,
        ContinueManuallyAssociatedAccessEventFlowResult $result
    ): void {
        $flow = $result->flow;

        activity('access_control')
            ->causedBy($user)
            ->performedOn($record)
            ->event(
                'access_event_manual_association_flow_continued'
            )
            ->withProperties([
                'status' => 'success',

                /*
                 * Identificador preservado para rastreabilidade,
                 * mas não apresentado no histórico amigável.
                 */
                'association_id' => $result->associationId,

                'processing_status' => $flow->processing
                    ->status
                    ->value,

                'processing_result_code' => $flow->processing
                    ->resultCode,

                'processing_attempts' => $flow->processing
                    ->processingAttempts,

                'processing_duplicate' => $flow->processing
                    ->duplicate,

                'decision_id' => $flow->decision
                    ->decisionId,

                'decision_version' => $flow->decision
                    ->version,

                'decision' => $flow->decision
                    ->decision
                    ->value,

                'decision_reason_code' => $flow->decision
                    ->reasonCode,

                'decision_duplicate' => $flow->decision
                    ->duplicate,

                'automatic_execution_enabled' => $flow->decision
                    ->automaticExecutionEnabled,

                'execution_id' => $flow->registration
                    ->executionId,

                'execution_attempt_number' => $flow->registration
                    ->attemptNumber,

                'execution_source' => $flow->registration
                    ->source
                    ->value,

                'execution_status' => $flow->registration
                    ->status
                    ->value,

                'execution_reason_code' => $flow->registration
                    ->reasonCode,

                'execution_duplicate' => $flow->registration
                    ->duplicate,

                'automatic_execution_allowed' => $flow->registration
                    ->automaticExecutionAllowed,

                'operation_status' => $flow->execution
                    ?->status
                    ->value,

                'operation_reason_code' => $flow->execution
                    ?->reasonCode,

                'visit_status_before' => $flow->execution
                    ?->visitStatusBefore
                    ?->value,

                'visit_status_after' => $flow->execution
                    ?->visitStatusAfter
                    ?->value,

                'all_duplicates' => self::allResultsAreDuplicates(
                    $flow
                ),

                'message' => self::resultMessage(
                    $flow
                ),
            ])
            ->log(
                'Fluxo continuado após associação manual'
            );
    }

    private static function auditFailure(
        AccessEventRecord $record,
        User $user,
        ContinueManuallyAssociatedAccessEventFlowException $exception
    ): void {
        activity('access_control')
            ->causedBy($user)
            ->performedOn($record)
            ->event(
                'access_event_manual_association_flow_continued'
            )
            ->withProperties([
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ])
            ->log(
                'Falha ao continuar fluxo após associação manual'
            );
    }
}
