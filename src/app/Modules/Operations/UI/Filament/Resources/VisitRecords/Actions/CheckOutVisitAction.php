<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\Visits\CheckOutVisit\CheckOutVisitCommand;
use App\Modules\Operations\Application\Visits\CheckOutVisit\CheckOutVisitUseCase;
use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

final class CheckOutVisitAction
{
    public static function make(): Action
    {
        return Action::make('checkOutVisit')
            ->label('Registrar saída')
            ->tooltip('Registrar saída manual')
            ->icon('heroicon-o-arrow-left-start-on-rectangle')
            ->iconButton()
            ->color('info')
            ->requiresConfirmation()
            ->closeModalByClickingAway(false)
            ->modalHeading('Registrar saída manual')
            ->modalDescription(
                'Use esta ação somente como contingência quando o equipamento ou a integração automática não registrar a saída. A operação ficará vinculada ao usuário atual.'
            )
            ->modalSubmitActionLabel('Registrar saída')
            ->visible(
                fn (VisitRecord $record): bool => self::isEligible(
                    $record
                )
                    && (
                        auth()->user()?->can(
                            'operateGatehouse',
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
                        'operateGatehouse',
                        $record
                    );

                    try {
                        app(
                            CheckOutVisitUseCase::class
                        )->execute(
                            new CheckOutVisitCommand(
                                visitId: $record->id,
                                operatorUserId: (int) $user->id,
                            )
                        );

                        $record->refresh();

                        Notification::make()
                            ->title('Saída registrada')
                            ->body(
                                'A saída manual foi registrada como contingência.'
                            )
                            ->success()
                            ->send();
                    } catch (
                        VisitOperationException $exception
                    ) {
                        Notification::make()
                            ->title(
                                'Não foi possível registrar a saída'
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

        return $record->checked_out_at === null
            && ($status?->canCheckOut() ?? false);
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
