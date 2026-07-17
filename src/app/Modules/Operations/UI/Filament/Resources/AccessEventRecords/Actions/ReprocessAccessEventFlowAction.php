<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowResult;
use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowCommand;
use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowException;
use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class ReprocessAccessEventFlowAction
{
    public static function make(): Action
    {
        return Action::make(
            'reprocessAccessEventFlow'
        )
            ->label('Reprocessar fluxo')
            ->tooltip('Reprocessar fluxo')
            ->icon(
                'heroicon-o-arrow-path'
            )
            ->iconButton()
            ->color('warning')
            ->requiresConfirmation()
            ->closeModalByClickingAway(false)
            ->modalHeading(
                'Reprocessar fluxo do evento'
            )
            ->modalDescription(
                'O VANGUARD repetirá o processamento, a decisão operacional e o registro da tentativa. Se o ambiente estiver em modo primário e as operações automáticas estiverem habilitadas, uma entrada ou saída poderá ser executada. Esta ação não envia comandos físicos ao leitor.'
            )
            ->modalSubmitActionLabel(
                'Reprocessar fluxo'
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
                            ReprocessAccessEventFlowUseCase::class
                        )->execute(
                            new ReprocessAccessEventFlowCommand(
                                eventId: $record->id,
                                operatorUserId: (int) $user->id,
                            )
                        );

                        self::auditSuccess(
                            $record,
                            $result
                        );

                        self::sendResultNotification(
                            $result
                        );
                    } catch (
                        ReprocessAccessEventFlowException $exception
                    ) {
                        self::auditFailure(
                            $record,
                            $exception
                        );

                        Notification::make()
                            ->title(
                                'Não foi possível reprocessar o fluxo'
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
        if (
            $record->relationLoaded(
                'latestOperationalDecision'
            )
        ) {
            $decision = $record->getRelation(
                'latestOperationalDecision'
            );
        } elseif (
            ! $record->exists
            || $record->getKey() === null
            || $record->getKey() === ''
        ) {
            $decision = null;
        } else {
            $decision = $record
                ->latestOperationalDecision()
                ->first();
        }

        if (
            ! $decision
            instanceof AccessEventOperationalDecisionRecord
        ) {
            return true;
        }

        $decisionState = $decision->decision;

        if (
            ! $decisionState
            instanceof AccessEventOperationalDecision
        ) {
            $decisionState =
                AccessEventOperationalDecision::tryFrom(
                    (string) $decisionState
                );
        }

        if (
            $decisionState
            !== AccessEventOperationalDecision::ManualReview
        ) {
            return true;
        }

        if (
            $record->relationLoaded(
                'latestManualReview'
            )
        ) {
            $review = $record->getRelation(
                'latestManualReview'
            );
        } elseif (
            ! $record->exists
            || $record->getKey() === null
            || $record->getKey() === ''
        ) {
            $review = null;
        } else {
            $review = $record
                ->latestManualReview()
                ->first();
        }

        if (
            ! $review
            instanceof AccessEventManualReviewRecord
        ) {
            return false;
        }

        if (
            (string) $review->operational_decision_id
                !== (string) $decision->id
            || (int) $review->decision_version
                !== (int) $decision->version
        ) {
            return false;
        }

        $disposition = $review->disposition;

        if (
            ! $disposition
            instanceof AccessEventManualReviewDisposition
        ) {
            $disposition =
                AccessEventManualReviewDisposition::tryFrom(
                    (string) $disposition
                );
        }

        return $disposition
            instanceof AccessEventManualReviewDisposition
            && $disposition->requestsReprocessing();
    }

    private static function sendResultNotification(
        ContinueAccessEventFlowResult $result
    ): void {
        $allDuplicates =
            self::allResultsAreDuplicates(
                $result
            );

        $notification = Notification::make()
            ->title(
                self::notificationTitle(
                    $result,
                    $allDuplicates
                )
            )
            ->body(
                self::resultMessage($result)
            );

        if (
            $result->registration->status
            === AccessEventOperationalExecutionStatus::Failed
        ) {
            $notification->danger();
        } elseif (
            in_array(
                $result->registration->status,
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
        ContinueAccessEventFlowResult $result,
        bool $allDuplicates
    ): string {
        if ($allDuplicates) {
            return 'Fluxo reprocessado sem novas alterações';
        }

        if (
            $result->execution?->status
            === AccessEventOperationalExecutionStatus::Executed
        ) {
            return 'Fluxo reprocessado e operação concluída';
        }

        return match (
            $result->registration->status
        ) {
            AccessEventOperationalExecutionStatus::Blocked => 'Fluxo reprocessado com execução bloqueada',

            AccessEventOperationalExecutionStatus::Skipped => 'Fluxo reprocessado sem operação executável',

            AccessEventOperationalExecutionStatus::Failed => 'Fluxo reprocessado com falha',

            default => 'Fluxo reprocessado',
        };
    }

    private static function resultMessage(
        ContinueAccessEventFlowResult $result
    ): string {
        return collect([
            'Processamento: '
                .$result->processing
                    ->status
                    ->label()
                .'.',

            'Decisão: '
                .$result->decision
                    ->decision
                    ->label()
                .'.',

            'Tentativa: '
                .$result->registration
                    ->status
                    ->label()
                .'.',

            'Motivo: '
                .self::reasonLabel(
                    $result->registration
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
        ContinueAccessEventFlowResult $result
    ): bool {
        return $result->processing->duplicate
            && $result->decision->duplicate
            && $result->registration->duplicate
            && (
                $result->execution === null
                || $result->execution->duplicate
            );
    }

    private static function auditSuccess(
        AccessEventRecord $record,
        ContinueAccessEventFlowResult $result
    ): void {
        $activity = activity(
            'access_control'
        )
            ->performedOn($record)
            ->event(
                'access_event_flow_reprocessed'
            )
            ->withProperties([
                'status' => 'success',

                'processing_status' => $result->processing
                    ->status
                    ->value,

                'processing_result_code' => $result->processing
                    ->resultCode,

                'processing_attempts' => $result->processing
                    ->processingAttempts,

                'processing_duplicate' => $result->processing
                    ->duplicate,

                'decision_id' => $result->decision
                    ->decisionId,

                'decision_version' => $result->decision
                    ->version,

                'decision' => $result->decision
                    ->decision
                    ->value,

                'decision_reason_code' => $result->decision
                    ->reasonCode,

                'decision_duplicate' => $result->decision
                    ->duplicate,

                'automatic_execution_enabled' => $result->decision
                    ->automaticExecutionEnabled,

                'execution_id' => $result->registration
                    ->executionId,

                'execution_attempt_number' => $result->registration
                    ->attemptNumber,

                'execution_source' => $result->registration
                    ->source
                    ->value,

                'execution_status' => $result->registration
                    ->status
                    ->value,

                'execution_reason_code' => $result->registration
                    ->reasonCode,

                'execution_duplicate' => $result->registration
                    ->duplicate,

                'automatic_execution_allowed' => $result->registration
                    ->automaticExecutionAllowed,

                'operation_status' => $result->execution
                    ?->status
                    ->value,

                'operation_reason_code' => $result->execution
                    ?->reasonCode,

                'visit_status_before' => $result->execution
                    ?->visitStatusBefore
                    ?->value,

                'visit_status_after' => $result->execution
                    ?->visitStatusAfter
                    ?->value,

                'all_duplicates' => self::allResultsAreDuplicates(
                    $result
                ),

                'message' => self::resultMessage($result),
            ]);

        self::applyCauser($activity);

        $activity->log(
            'Fluxo do evento de acesso reprocessado'
        );
    }

    private static function auditFailure(
        AccessEventRecord $record,
        ReprocessAccessEventFlowException $exception
    ): void {
        $activity = activity(
            'access_control'
        )
            ->performedOn($record)
            ->event(
                'access_event_flow_reprocessed'
            )
            ->withProperties([
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ]);

        self::applyCauser($activity);

        $activity->log(
            'Falha ao reprocessar fluxo do evento de acesso'
        );
    }

    private static function applyCauser(
        mixed $activity
    ): void {
        $user = auth()->user();

        if ($user instanceof User) {
            $activity->causedBy($user);
        }
    }
}
