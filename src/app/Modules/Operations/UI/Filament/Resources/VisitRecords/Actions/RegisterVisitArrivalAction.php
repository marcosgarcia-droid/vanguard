<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\Visits\RegisterVisitArrival\RegisterVisitArrivalCommand;
use App\Modules\Operations\Application\Visits\RegisterVisitArrival\RegisterVisitArrivalUseCase;
use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

final class RegisterVisitArrivalAction
{
    public static function make(): Action
    {
        return Action::make('registerVisitArrival')
            ->label('Registrar chegada')
            ->tooltip('Registrar chegada')
            ->icon('heroicon-o-map-pin')
            ->iconButton()
            ->color('warning')
            ->requiresConfirmation()
            ->closeModalByClickingAway(false)
            ->modalHeading('Registrar chegada do visitante')
            ->modalDescription(
                'Confirme que o visitante chegou à portaria e que sua identidade foi conferida.'
            )
            ->modalSubmitActionLabel('Registrar chegada')
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
                        app(
                            RegisterVisitArrivalUseCase::class
                        )->execute(
                            new RegisterVisitArrivalCommand(
                                visitId: $record->id,
                                operatorUserId: (int) $user->id,
                            )
                        );

                        $record->refresh();

                        Notification::make()
                            ->title('Chegada registrada')
                            ->body(
                                'A chegada e a conferência de identidade foram registradas.'
                            )
                            ->success()
                            ->send();
                    } catch (
                        VisitOperationException $exception
                    ) {
                        Notification::make()
                            ->title(
                                'Não foi possível registrar a chegada'
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
        if ($record->arrived_at !== null) {
            return false;
        }

        $status = $record->status;

        if (! $status instanceof VisitStatus) {
            $status = VisitStatus::tryFrom(
                (string) $status
            );
        }

        return $status?->canRegisterArrival() ?? false;
    }
}
