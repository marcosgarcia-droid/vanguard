<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\Visits\RejectVisit\RejectVisitCommand;
use App\Modules\Operations\Application\Visits\RejectVisit\RejectVisitUseCase;
use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

final class RejectVisitAction
{
    public static function make(): Action
    {
        return Action::make('rejectVisit')
            ->label('Não autorizar')
            ->tooltip('Não autorizar visita')
            ->icon('heroicon-o-x-circle')
            ->iconButton()
            ->color('danger')
            ->closeModalByClickingAway(false)
            ->modalHeading('Não autorizar visita')
            ->modalDescription(
                'Registre o motivo da não autorização. A visita permanecerá no histórico.'
            )
            ->modalSubmitActionLabel('Não autorizar')
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
                        app(
                            RejectVisitUseCase::class
                        )->execute(
                            new RejectVisitCommand(
                                visitId: $record->id,
                                operatorUserId: (int) $user->id,
                                reason: $data['rejection_reason']
                                    ?? null,
                            )
                        );

                        $record->refresh();

                        Notification::make()
                            ->title('Visita não autorizada')
                            ->success()
                            ->send();
                    } catch (
                        VisitOperationException $exception
                    ) {
                        Notification::make()
                            ->title(
                                'Não foi possível recusar a visita'
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

        return $status !== VisitStatus::Rejected
            && ($status?->canReject() ?? false);
    }
}
