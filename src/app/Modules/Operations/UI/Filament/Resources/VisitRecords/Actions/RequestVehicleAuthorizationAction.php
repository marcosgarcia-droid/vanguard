<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Actions;

use App\Models\User;
use App\Modules\Operations\Application\Visits\RequestVehicleAuthorization\RequestVisitVehicleAuthorizationCommand;
use App\Modules\Operations\Application\Visits\RequestVehicleAuthorization\RequestVisitVehicleAuthorizationUseCase;
use App\Modules\Operations\Application\Visits\VehicleAuthorization\VisitVehicleAuthorizationException;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Notifications\VisitVehicleAuthorizationNotifier;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

final class RequestVehicleAuthorizationAction
{
    public static function make(): Action
    {
        return Action::make('requestVehicleAuthorization')
            ->label('Solicitar autorização do veículo')
            ->tooltip('Solicitar autorização do veículo')
            ->icon('heroicon-o-truck')
            ->iconButton()
            ->color('warning')
            ->closeModalByClickingAway(false)
            ->modalHeading('Solicitar autorização do veículo')
            ->modalDescription(
                'Registre a solicitação para que um Gestor decida sobre a entrada do veículo.'
            )
            ->modalSubmitActionLabel('Enviar solicitação')
            ->form([
                Textarea::make('request_notes')
                    ->label('Observações da solicitação')
                    ->rows(4)
                    ->maxLength(2000),
            ])
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
                        'operateGatehouse',
                        $record
                    );

                    $vehicle = $record->vehicle;

                    if ($vehicle === null) {
                        Notification::make()
                            ->title('Veículo não encontrado')
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    try {
                        $request = app(
                            RequestVisitVehicleAuthorizationUseCase::class
                        )->execute(
                            new RequestVisitVehicleAuthorizationCommand(
                                visitVehicleId: (int) $vehicle->id,
                                tenantId: (string) $record->tenant_id,
                                organizationId: (string) $record->organization_id,
                                requestedByUserId: (int) $user->id,
                                idempotencyKey: (string) Str::uuid(),
                                notes: $data['request_notes']
                                    ?? null,
                            )
                        );

                        if ($request->wasRecentlyCreated) {
                            try {
                                app(
                                    VisitVehicleAuthorizationNotifier::class
                                )->notifyRequestCreated($request);
                            } catch (Throwable $notificationException) {
                                report($notificationException);
                            }
                        }

                        $record->refresh();

                        Notification::make()
                            ->title(
                                'Autorização do veículo solicitada'
                            )
                            ->body(
                                'A solicitação foi registrada e aguarda decisão de um Gestor.'
                            )
                            ->success()
                            ->send();
                    } catch (
                        VisitVehicleAuthorizationException $exception
                    ) {
                        Notification::make()
                            ->title(
                                'Não foi possível solicitar a autorização'
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
            && $vehicle->latestAuthorizationRequest === null;
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
