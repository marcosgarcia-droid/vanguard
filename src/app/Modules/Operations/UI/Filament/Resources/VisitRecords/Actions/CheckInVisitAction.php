<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\Visits\CheckInVisit\CheckInVisitCommand;
use App\Modules\Operations\Application\Visits\CheckInVisit\CheckInVisitUseCase;
use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

final class CheckInVisitAction
{
    public static function make(): Action
    {
        return Action::make('checkInVisit')
            ->label('Registrar entrada')
            ->tooltip('Registrar entrada manual')
            ->icon('heroicon-o-arrow-right-end-on-rectangle')
            ->iconButton()
            ->color('success')
            ->requiresConfirmation()
            ->closeModalByClickingAway(false)
            ->modalHeading('Registrar entrada manual')
            ->modalDescription(
                'Use esta ação somente como contingência quando o equipamento ou a integração automática não registrar a entrada. A operação ficará vinculada ao usuário atual.'
            )
            ->modalSubmitActionLabel('Registrar entrada')
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
                function (VisitRecord $record): void {
                    $user = auth()->user();

                    if (! $user instanceof User) {
                        self::operatorNotFound();

                        return;
                    }

                    Gate::authorize(
                        'update',
                        $record
                    );

                    try {
                        app(
                            CheckInVisitUseCase::class
                        )->execute(
                            new CheckInVisitCommand(
                                visitId: $record->id,
                                operatorUserId: (int) $user->id,
                            )
                        );

                        $record->refresh();

                        Notification::make()
                            ->title('Entrada registrada')
                            ->body(
                                'A entrada manual foi registrada como contingência.'
                            )
                            ->success()
                            ->send();
                    } catch (
                        VisitOperationException $exception
                    ) {
                        Notification::make()
                            ->title(
                                'Não foi possível registrar a entrada'
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

        return $record->checked_in_at === null
            && ($status?->canCheckIn() ?? false);
    }

    private static function operatorNotFound(): void
    {
        Notification::make()
            ->title(
                'Não foi possível identificar o operador'
            )
            ->danger()
            ->persistent()
            ->send();
    }
}
