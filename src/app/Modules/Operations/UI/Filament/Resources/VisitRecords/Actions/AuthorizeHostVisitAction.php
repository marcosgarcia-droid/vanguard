<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\Visits\AuthorizeVisit\AuthorizeVisitCommand;
use App\Modules\Operations\Application\Visits\AuthorizeVisit\AuthorizeVisitUseCase;
use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visits\VisitAuthorizationMethod;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Notifications\VisitGatehouseDecisionNotifier;
use App\Modules\Operations\UI\Notifications\VisitHostNotifier;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

final class AuthorizeHostVisitAction
{
    public static function make(): Action
    {
        return Action::make('authorizeHostVisit')
            ->label('Autorizar meu visitante')
            ->tooltip('Autorizar meu visitante')
            ->icon('heroicon-o-check-circle')
            ->iconButton()
            ->color('success')
            ->closeModalByClickingAway(false)
            ->modalHeading('Autorizar meu visitante')
            ->modalDescription(
                'Confirme a autorização de entrada do visitante pelo sistema.'
            )
            ->modalSubmitActionLabel('Autorizar visita')
            ->form([
                Textarea::make('authorization_notes')
                    ->label('Observações')
                    ->rows(4)
                    ->maxLength(2000),
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
                            AuthorizeVisitUseCase::class
                        )->execute(
                            new AuthorizeVisitCommand(
                                visitId: $record->id,
                                authorizerEmployeeId: (string) $record
                                    ->host_employee_id,
                                recordedByUserId: (int) $user->id,
                                method: VisitAuthorizationMethod::System,
                                notes: $data['authorization_notes']
                                    ?? null,
                            )
                        );

                        if ($visit->wasChanged('authorized_at')) {
                            app(
                                VisitHostNotifier::class
                            )->closeDecisionActions(
                                $visit
                            );

                            app(
                                VisitGatehouseDecisionNotifier::class
                            )->notifyAuthorizedByHost(
                                $visit,
                                (int) $user->id
                            );
                        }

                        $record->refresh();

                        Notification::make()
                            ->title('Visitante autorizado')
                            ->success()
                            ->send();
                    } catch (
                        VisitOperationException $exception
                    ) {
                        Notification::make()
                            ->title(
                                'Não foi possível autorizar o visitante'
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

        return $status?->canAuthorize() ?? false;
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
