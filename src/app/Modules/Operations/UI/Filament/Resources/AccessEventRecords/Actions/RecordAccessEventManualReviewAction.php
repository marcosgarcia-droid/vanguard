<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\AccessControl\Events\ManualReview\RecordAccessEventManualReviewCommand;
use App\Modules\Operations\Application\AccessControl\Events\ManualReview\RecordAccessEventManualReviewException;
use App\Modules\Operations\Application\AccessControl\Events\ManualReview\RecordAccessEventManualReviewResult;
use App\Modules\Operations\Application\AccessControl\Events\ManualReview\RecordAccessEventManualReviewUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class RecordAccessEventManualReviewAction
{
    public static function make(): Action
    {
        return Action::make(
            'recordAccessEventManualReview'
        )
            ->label('Registrar análise')
            ->tooltip('Registrar análise manual')
            ->icon(
                'heroicon-o-clipboard-document-check'
            )
            ->iconButton()
            ->color('warning')
            ->closeModalByClickingAway(false)
            ->modalHeading(
                'Registrar análise do evento'
            )
            ->modalDescription(
                'Registre o resultado da avaliação humana. Esta ação não registra entrada ou saída, não reprocessa o evento e não envia comandos ao equipamento.'
            )
            ->modalSubmitActionLabel(
                'Registrar análise'
            )
            ->form([
                Select::make('disposition')
                    ->label('Resultado da análise')
                    ->options(
                        self::dispositionOptions()
                    )
                    ->default(
                        AccessEventManualReviewDisposition::PendingCorrection
                            ->value
                    )
                    ->required()
                    ->native(false)
                    ->helperText(
                        'Use “Pronto para reprocessamento” somente depois que a pendência que originou a revisão tiver sido corrigida.'
                    ),

                Textarea::make('notes')
                    ->label('Observações')
                    ->required()
                    ->rows(5)
                    ->minLength(10)
                    ->maxLength(2000)
                    ->helperText(
                        'Descreva o que foi verificado, qual correção é necessária ou por que o evento pode ser encerrado sem operação.'
                    ),

                Hidden::make('idempotency_key')
                    ->default(
                        fn (): string => (string) Str::uuid()
                    )
                    ->required(),
            ])
            ->visible(
                fn (
                    AccessEventRecord $record
                ): bool => self::isEligibleRecord(
                    $record
                )
                    && (
                        auth()->user()?->can(
                            'resolveManualReview',
                            $record
                        ) ?? false
                    )
            )
            ->action(
                function (
                    AccessEventRecord $record,
                    array $data
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
                        'resolveManualReview',
                        $record
                    );

                    try {
                        $disposition =
                            self::dispositionFromData(
                                $data
                            );

                        $notes = trim(
                            (string) (
                                $data['notes']
                                ?? ''
                            )
                        );

                        $result = app(
                            RecordAccessEventManualReviewUseCase::class
                        )->execute(
                            new RecordAccessEventManualReviewCommand(
                                eventId: $record->id,
                                operatorUserId: (int) $user->id,
                                disposition: $disposition,
                                notes: $notes,
                                idempotencyKey: (string) (
                                    $data['idempotency_key']
                                    ?? ''
                                ),
                            )
                        );

                        $record
                            ->refresh()
                            ->load([
                                'latestOperationalDecision',
                                'latestManualReview',
                            ]);

                        self::auditSuccess(
                            record: $record,
                            user: $user,
                            result: $result,
                            notes: $notes,
                        );

                        self::sendSuccessNotification(
                            $result
                        );
                    } catch (
                        RecordAccessEventManualReviewException $exception
                    ) {
                        self::auditFailure(
                            record: $record,
                            user: $user,
                            exception: $exception,
                        );

                        Notification::make()
                            ->title(
                                'Não foi possível registrar a análise'
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

    /**
     * @return array<string, string>
     */
    public static function dispositionOptions(): array
    {
        return collect(
            AccessEventManualReviewDisposition::cases()
        )
            ->mapWithKeys(
                fn (
                    AccessEventManualReviewDisposition $disposition
                ): array => [
                    $disposition->value => $disposition->label(),
                ]
            )
            ->all();
    }

    public static function isEligibleRecord(
        AccessEventRecord $record
    ): bool {
        $decision = $record->relationLoaded(
            'latestOperationalDecision'
        )
            ? $record->getRelation(
                'latestOperationalDecision'
            )
            : $record
                ->latestOperationalDecision()
                ->first();

        if (
            ! $decision
            instanceof AccessEventOperationalDecisionRecord
        ) {
            return false;
        }

        $state = $decision->decision;

        if (
            ! $state
            instanceof AccessEventOperationalDecision
        ) {
            $state =
                AccessEventOperationalDecision::tryFrom(
                    (string) $state
                );
        }

        if (
            $state
            !== AccessEventOperationalDecision::ManualReview
        ) {
            return false;
        }

        if (
            $record->relationLoaded(
                'latestManualReview'
            )
        ) {
            $latestReview = $record->getRelation(
                'latestManualReview'
            );
        } elseif (
            ! $record->exists
            || $record->getKey() === null
            || $record->getKey() === ''
        ) {
            /*
             * Models não persistidos são usados nos testes
             * unitários da elegibilidade e não possuem ledger.
             */
            $latestReview = null;
        } else {
            $latestReview = $record
                ->latestManualReview()
                ->first();
        }

        if (
            ! $latestReview
            instanceof AccessEventManualReviewRecord
        ) {
            return true;
        }

        $disposition =
            $latestReview->disposition;

        if (
            ! $disposition
            instanceof AccessEventManualReviewDisposition
        ) {
            $disposition =
                AccessEventManualReviewDisposition::tryFrom(
                    (string) $disposition
                );
        }

        return ! (
            $disposition
            instanceof AccessEventManualReviewDisposition
            && $disposition->isResolved()
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function dispositionFromData(
        array $data
    ): AccessEventManualReviewDisposition {
        $disposition =
            AccessEventManualReviewDisposition::tryFrom(
                trim(
                    (string) (
                        $data['disposition']
                        ?? ''
                    )
                )
            );

        if (
            ! $disposition
            instanceof AccessEventManualReviewDisposition
        ) {
            throw new RecordAccessEventManualReviewException(
                'Selecione um resultado válido para a análise.'
            );
        }

        return $disposition;
    }

    private static function sendSuccessNotification(
        RecordAccessEventManualReviewResult $result
    ): void {
        if ($result->duplicate) {
            Notification::make()
                ->title(
                    'Análise manual já registrada'
                )
                ->body(
                    'A solicitação já havia sido concluída e nenhum registro duplicado foi criado.'
                )
                ->success()
                ->send();

            return;
        }

        $notification = Notification::make()
            ->success();

        match ($result->disposition) {
            AccessEventManualReviewDisposition::PendingCorrection => $notification
                ->title(
                    'Pendência registrada'
                )
                ->body(
                    'A análise foi registrada e permanece aguardando correção. Nenhuma entrada ou saída foi executada.'
                ),

            AccessEventManualReviewDisposition::ReadyForReprocessing => $notification
                ->title(
                    'Evento pronto para reprocessamento'
                )
                ->body(
                    'A correção foi registrada. Use “Reprocessar fluxo” para calcular uma nova decisão operacional.'
                ),

            AccessEventManualReviewDisposition::ResolvedWithoutOperation => $notification
                ->title(
                    'Revisão encerrada'
                )
                ->body(
                    'O evento foi encerrado na análise sem registrar entrada ou saída.'
                ),
        };

        $notification->send();
    }

    private static function auditSuccess(
        AccessEventRecord $record,
        User $user,
        RecordAccessEventManualReviewResult $result,
        string $notes
    ): void {
        $decision =
            $record->latestOperationalDecision;

        activity('access_control')
            ->causedBy($user)
            ->performedOn($record)
            ->event(
                'access_event_manual_review_recorded'
            )
            ->withProperties([
                'status' => 'success',

                /*
                 * Identificadores preservados para rastreabilidade,
                 * mas não apresentados no histórico amigável.
                 */
                'review_id' => $result->reviewId,
                'decision_id' => $result->decisionId,

                'disposition' => $result->disposition->value,

                'disposition_label' => $result->disposition->label(),

                'decision_version' => $decision?->version,

                'decision_reason_code' => $decision?->reason_code,

                'decision_reason_message' => $decision?->reason_message,

                'notes' => $notes,
                'duplicate' => $result->duplicate,

                'reviewed_at' => $result->reviewedAt
                    ->toIso8601String(),

                'message' => self::successMessage(
                    $result
                ),
            ])
            ->log(
                'Análise manual do evento de acesso registrada'
            );
    }

    private static function auditFailure(
        AccessEventRecord $record,
        User $user,
        RecordAccessEventManualReviewException $exception
    ): void {
        activity('access_control')
            ->causedBy($user)
            ->performedOn($record)
            ->event(
                'access_event_manual_review_recorded'
            )
            ->withProperties([
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ])
            ->log(
                'Falha ao registrar análise manual do evento de acesso'
            );
    }

    private static function successMessage(
        RecordAccessEventManualReviewResult $result
    ): string {
        if ($result->duplicate) {
            return 'A análise manual já havia sido registrada e nenhum novo registro foi criado.';
        }

        return match ($result->disposition) {
            AccessEventManualReviewDisposition::PendingCorrection => 'A pendência foi confirmada e permanece aguardando correção.',

            AccessEventManualReviewDisposition::ReadyForReprocessing => 'A correção foi registrada e o evento está pronto para reprocessamento manual.',

            AccessEventManualReviewDisposition::ResolvedWithoutOperation => 'A revisão foi encerrada sem registrar uma operação de entrada ou saída.',
        };
    }
}
