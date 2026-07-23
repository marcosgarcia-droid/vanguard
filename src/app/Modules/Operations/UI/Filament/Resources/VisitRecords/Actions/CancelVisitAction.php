<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\Visits\CancelVisit\CancelVisitCommand;
use App\Modules\Operations\Application\Visits\CancelVisit\CancelVisitUseCase;
use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Notifications\VisitHostNotifier;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class CancelVisitAction
{
    public static function make(): Action
    {
        return Action::make('cancelVisit')
            ->label('Cancelar')
            ->tooltip('Cancelar visita')
            ->icon('heroicon-o-no-symbol')
            ->iconButton()
            ->color('gray')
            ->closeModalByClickingAway(false)
            ->modalHeading('Cancelar visita')
            ->modalDescription(
                'A visita será cancelada e permanecerá disponível no histórico.'
            )
            ->modalSubmitActionLabel('Cancelar visita')
            ->form([
                Textarea::make('cancellation_reason')
                    ->label('Motivo do cancelamento')
                    ->required()
                    ->minLength(5)
                    ->maxLength(2000)
                    ->rows(5),
            ])
            ->visible(
                fn (VisitRecord $record): bool => self::isEligible(
                    $record
                )
                    && (
                        auth()->user()?->can(
                            'update',
                            $record
                        ) ?? false
                    )
            )
            ->action(
                function (
                    VisitRecord $record,
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
                        'update',
                        $record
                    );

                    try {
                        $visit = app(
                            CancelVisitUseCase::class
                        )->execute(
                            new CancelVisitCommand(
                                visitId: $record->id,
                                operatorUserId: (int) $user->id,
                                reason: $data['cancellation_reason']
                                    ?? null,
                            )
                        );

                        $notificationFailed = false;

                        if (
                            $visit->wasChanged(
                                'cancelled_at'
                            )
                        ) {
                            try {
                                $notifier = app(
                                    VisitHostNotifier::class
                                );

                                $notifier->closeDecisionActions(
                                    $visit
                                );

                                $notifier->notifyCancelled(
                                    $visit,
                                    (int) $user->id
                                );
                            } catch (
                                Throwable $notificationException
                            ) {
                                report(
                                    $notificationException
                                );

                                $notificationFailed = true;
                            }
                        }

                        $record->refresh();

                        $notification = Notification::make();

                        if ($notificationFailed) {
                            $notification
                                ->title(
                                    'Visita cancelada, mas o aviso ao visitado não foi enviado'
                                )
                                ->body(
                                    'O cancelamento foi salvo normalmente e poderá ser consultado no histórico.'
                                )
                                ->warning();
                        } else {
                            $notification
                                ->title('Visita cancelada')
                                ->success();
                        }

                        $notification->send();
                    } catch (
                        VisitOperationException $exception
                    ) {
                        Notification::make()
                            ->title(
                                'Não foi possível cancelar a visita'
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

    public static function isEligible(
        VisitRecord $record
    ): bool {
        $status = $record->status;

        if (! $status instanceof VisitStatus) {
            $status = VisitStatus::tryFrom(
                (string) $status
            );
        }

        return $status !== VisitStatus::Cancelled
            && ($status?->canCancel() ?? false);
    }
}
