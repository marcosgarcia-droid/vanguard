<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\Visits\RejectVisit\RejectVisitCommand;
use App\Modules\Operations\Application\Visits\RejectVisit\RejectVisitUseCase;
use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Notifications\VisitGatehouseDecisionNotifier;
use App\Modules\Operations\UI\Notifications\VisitHostNotifier;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

final class RejectHostVisitAction
{
    public static function make(): Action
    {
        return Action::make('rejectHostVisit')
            ->label('Não autorizar meu visitante')
            ->tooltip('Não autorizar meu visitante')
            ->icon('heroicon-o-x-circle')
            ->iconButton()
            ->color('danger')
            ->closeModalByClickingAway(false)
            ->modalHeading('Não autorizar meu visitante')
            ->modalDescription(
                'Informe o motivo da não autorização. A visita permanecerá no histórico.'
            )
            ->modalSubmitActionLabel('Não autorizar visita')
            ->form([
                Textarea::make('rejection_reason')
                    ->label('Motivo')
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
                            'decideAsHost',
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
                        self::userNotFound();

                        return;
                    }

                    Gate::authorize(
                        'decideAsHost',
                        $record
                    );

                    try {
                        $visit = app(
                            RejectVisitUseCase::class
                        )->execute(
                            new RejectVisitCommand(
                                visitId: $record->id,
                                operatorUserId: (int) $user->id,
                                reason: $data['rejection_reason']
                                    ?? null,
                            )
                        );

                        if ($visit->wasChanged('rejected_at')) {
                            app(
                                VisitHostNotifier::class
                            )->closeDecisionActions(
                                $visit
                            );

                            app(
                                VisitGatehouseDecisionNotifier::class
                            )->notifyRejectedByHost(
                                $visit,
                                (int) $user->id
                            );
                        }

                        $record->refresh();

                        Notification::make()
                            ->title('Visitante não autorizado')
                            ->success()
                            ->send();
                    } catch (
                        VisitOperationException $exception
                    ) {
                        Notification::make()
                            ->title(
                                'Não foi possível recusar o visitante'
                            )
                            ->body($exception->getMessage())
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

        return $status !== VisitStatus::Rejected
            && ($status?->canReject() ?? false);
    }

    private static function userNotFound(): void
    {
        Notification::make()
            ->title(
                'Não foi possível identificar o visitado'
            )
            ->danger()
            ->persistent()
            ->send();
    }
}
