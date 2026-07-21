<?php

namespace App\Modules\Operations\UI\Notifications;

use App\Models\User;
use App\Modules\Operations\Domain\Visits\VisitVehicleAuthorizationStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitVehicleAuthorizationRequestRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\VisitRecordResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class VisitVehicleAuthorizationNotifier
{
    private const AUTHORIZE_PERMISSION =
        'AuthorizeVehicleEntry:VisitRecord';

    public function notifyRequestCreated(
        VisitVehicleAuthorizationRequestRecord $request
    ): void {
        $request->loadMissing([
            'visit.visitor',
            'visit.organization',
            'vehicle',
        ]);

        $visit = $request->visit;

        if (! $visit instanceof VisitRecord) {
            return;
        }

        $recipients = $this->authorizationRecipients(
            $visit,
            $request->requested_by_user_id
        );

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Autorização de veículo pendente')
            ->body(
                sprintf(
                    '%s solicitou autorização para o veículo %s da visita de %s na unidade %s.',
                    $request->requested_by_name
                        ?: 'Um operador',
                    $request->vehicle?->plate
                        ?: 'sem placa informada',
                    $visit->visitor?->display_name
                        ?: 'visitante não identificado',
                    $this->organizationName($visit)
                )
            )
            ->warning()
            ->icon('heroicon-o-truck')
            ->actions([
                $this->openVisitsAction(),
            ])
            ->sendToDatabase(
                $recipients,
                isEventDispatched: true
            );
    }

    public function notifyDecision(
        VisitVehicleAuthorizationRequestRecord $request
    ): void {
        $request->loadMissing([
            'visit.visitor',
            'visit.organization',
            'vehicle',
            'requestedBy',
        ]);

        $recipient = $request->requestedBy;

        if (
            ! $recipient instanceof User
            || $recipient->id === $request->decided_by_user_id
        ) {
            return;
        }

        $authorized = $request->status
            === VisitVehicleAuthorizationStatus::Authorized;

        $body = sprintf(
            'A entrada do veículo %s da visita de %s foi %s por %s.',
            $request->vehicle?->plate
                ?: 'sem placa informada',
            $request->visit?->visitor?->display_name
                ?: 'visitante não identificado',
            $authorized ? 'autorizada' : 'recusada',
            $request->decided_by_name
                ?: 'um gestor'
        );

        if (
            ! $authorized
            && filled($request->decision_notes)
        ) {
            $body .= ' Motivo: '.Str::limit(
                trim((string) $request->decision_notes),
                240
            );
        }

        $notification = Notification::make()
            ->title(
                $authorized
                    ? 'Entrada do veículo autorizada'
                    : 'Entrada do veículo recusada'
            )
            ->body($body)
            ->icon(
                $authorized
                    ? 'heroicon-o-check-circle'
                    : 'heroicon-o-x-circle'
            )
            ->actions([
                $this->openVisitsAction(),
            ]);

        if ($authorized) {
            $notification->success();
        } else {
            $notification->danger();
        }

        $notification->sendToDatabase(
            $recipient,
            isEventDispatched: true
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function authorizationRecipients(
        VisitRecord $visit,
        ?int $excludedUserId
    ): Collection {
        $permissionUsers = User::permission(
            self::AUTHORIZE_PERMISSION
        )->get();

        $superAdminUsers = User::role(
            config(
                'filament-shield.super_admin.name',
                'super_admin'
            )
        )->get();

        return $permissionUsers
            ->merge($superAdminUsers)
            ->unique('id')
            ->reject(
                fn (User $user): bool => $user->id === $excludedUserId
            )
            ->filter(
                fn (User $user): bool => Gate::forUser($user)->allows(
                    'authorizeVehicleEntry',
                    $visit
                )
            )
            ->values();
    }

    private function organizationName(
        VisitRecord $visit
    ): string {
        $organization = $visit->organization;

        return $organization?->display_name
            ?: $organization?->trade_name
            ?: $organization?->legal_name
            ?: 'unidade não identificada';
    }

    private function openVisitsAction(): Action
    {
        return Action::make('openVisits')
            ->label('Abrir visitas')
            ->url(
                VisitRecordResource::getUrl('list')
            )
            ->markAsRead();
    }
}
