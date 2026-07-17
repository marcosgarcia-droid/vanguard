<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowResult;
use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowCommand;
use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowException;
use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowResult;
use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewConsumptionRecord;
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
                'O VANGUARD repetirá o processamento, a decisão operacional e o registro da tentativa. Se o ambiente estiver em modo primário e as operações automáticas estiverem habilitadas, uma entrada ou saída poderá ser executada. Quando houver liberação manual, ela será consumida uma única vez. Esta ação não envia comandos físicos ao leitor.'
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

                                idempotencyKey: (string) Str::uuid(),
                            )
                        );

                        self::auditSuccess(
                            $record,
                            $result
                        );

                        self::sendResultNotification(
                            $result->flow
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

        if (
            ! $disposition
            instanceof AccessEventManualReviewDisposition
            || ! $disposition->requestsReprocessing()
        ) {
            return false;
        }

        if (
            $review->relationLoaded(
                'reprocessConsumption'
            )
        ) {
            $consumption = $review->getRelation(
                'reprocessConsumption'
            );
        } elseif (
            ! $review->exists
            || $review->getKey() === null
            || $review->getKey() === ''
        ) {
            $consumption = null;
        } else {
            $consumption = $review
                ->reprocessConsumption()
                ->first();
        }

        return ! (
            $consumption
            instanceof AccessEventManualReviewConsumptionRecord
        );
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
        ReprocessAccessEventFlowResult $result
    ): void {
        $flow = $result->flow;

        $message = self::resultMessage(
            $flow
        );

        if ($result->manualReviewReleaseUsed) {
            $message .=
                ' A liberação da análise manual foi consumida.';
        }

        $activity = activity(
            'access_control'
        )
            ->performedOn($record)
            ->event(
                'access_event_flow_reprocessed'
            )
            ->withProperties([
                'status' => 'success',

                'manual_review_release_used' => $result->manualReviewReleaseUsed,

                'manual_review_release_consumed' => $result->manualReviewReleaseUsed,

                'manual_review_id' => $result->manualReviewId,

                'manual_review_consumption_id' => $result->manualReviewConsumptionId,

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

                'message' => $message,
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

                'manual_review_release_consumed' => $exception
                    ->manualReviewReleaseConsumed,

                'manual_review_id' => $exception->manualReviewId,

                'manual_review_consumption_id' => $exception
                    ->manualReviewConsumptionId,

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
