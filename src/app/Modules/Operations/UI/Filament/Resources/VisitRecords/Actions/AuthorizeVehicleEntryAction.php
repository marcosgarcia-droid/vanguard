<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\Visits\DecideVehicleAuthorization\DecideVisitVehicleAuthorizationCommand;
use App\Modules\Operations\Application\Visits\DecideVehicleAuthorization\DecideVisitVehicleAuthorizationUseCase;
use App\Modules\Operations\Application\Visits\VehicleAuthorization\VisitVehicleAuthorizationException;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Domain\Visits\VisitVehicleAuthorizationStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Notifications\VisitVehicleAuthorizationNotifier;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class AuthorizeVehicleEntryAction
{
    public static function make(): Action
    {
        return Action::make('authorizeVehicleEntry')
            ->label('Autorizar veículo')
            ->tooltip('Autorizar entrada do veículo')
            ->icon('heroicon-o-truck')
            ->iconButton()
            ->color('success')
            ->closeModalByClickingAway(false)
            ->modalHeading('Autorizar entrada do veículo')
            ->modalDescription(
                'Confirme a autorização para entrada do veículo vinculado à visita.'
            )
            ->modalSubmitActionLabel('Autorizar entrada')
            ->form([
                Textarea::make('decision_notes')
                    ->label('Observações da decisão')
                    ->rows(4)
                    ->maxLength(2000),
            ])
            ->visible(
                fn (VisitRecord $record): bool => self::isEligible(
                    $record
                )
                    && (
                        auth()->user()?->can(
                            'authorizeVehicleEntry',
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
                        self::operatorNotFound();

                        return;
                    }

                    Gate::authorize(
                        'authorizeVehicleEntry',
                        $record
                    );

                    $request = $record
                        ->vehicle
                        ?->pendingAuthorizationRequest;

                    if ($request === null) {
                        Notification::make()
                            ->title(
                                'Solicitação pendente não encontrada'
                            )
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    try {
                        $decidedRequest = app(
                            DecideVisitVehicleAuthorizationUseCase::class
                        )->execute(
                            new DecideVisitVehicleAuthorizationCommand(
                                requestId: (string) $request->id,
                                tenantId: (string) $record->tenant_id,
                                organizationId: (string) $record->organization_id,
                                decidedByUserId: (int) $user->id,
                                decision: VisitVehicleAuthorizationStatus::Authorized,
                                notes: $data['decision_notes']
                                    ?? null,
                            )
                        );

                        if ($decidedRequest->wasChanged('status')) {
                            try {
                                app(
                                    VisitVehicleAuthorizationNotifier::class
                                )->notifyDecision($decidedRequest);
                            } catch (Throwable $notificationException) {
                                report($notificationException);
                            }
                        }

                        $record->refresh();

                        Notification::make()
                            ->title(
                                'Entrada do veículo autorizada'
                            )
                            ->success()
                            ->send();
                    } catch (
                        VisitVehicleAuthorizationException $exception
                    ) {
                        Notification::make()
                            ->title(
                                'Não foi possível autorizar o veículo'
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

        if (! in_array($status, [
            VisitStatus::Scheduled,
            VisitStatus::PendingAuthorization,
            VisitStatus::Authorized,
        ], true)) {
            return false;
        }

        $vehicle = $record->vehicle;

        return $vehicle !== null
            && ! $vehicle->entry_authorized
            && $vehicle->pendingAuthorizationRequest !== null;
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
