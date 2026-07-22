<?php

namespace App\Modules\Operations\UI\Notifications;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\VisitRecordResource;
use Filament\Actions\Action;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;

final class VisitHostNotifier
{
    public function notifyScheduled(VisitRecord $visit): void
    {
        $recipient = $this->recipient($visit);

        if (! $recipient instanceof User) {
            return;
        }

        $notification = Notification::make()
            ->title('Nova visita agendada')
            ->body(
                sprintf(
                    'Você receberá %s em %s na unidade %s. Finalidade: %s.',
                    $this->visitorName($visit),
                    $this->scheduledFor($visit),
                    $this->organizationName($visit),
                    $this->purpose($visit)
                )
            )
            ->info()
            ->icon('heroicon-o-calendar-days')
            ->actions([
                $this->authorizeHostVisitAction($visit),
                $this->rejectHostVisitAction($visit),
                $this->openVisitsAction(),
            ]);

        $this->sendSynchronously(
            $recipient,
            $notification
        );
    }

    public function notifyArrival(VisitRecord $visit): void
    {
        $recipient = $this->recipient($visit);

        if (! $recipient instanceof User) {
            return;
        }

        $notification = Notification::make()
            ->title('Seu visitante chegou')
            ->body(
                sprintf(
                    '%s chegou à portaria da unidade %s. Finalidade: %s.',
                    $this->visitorName($visit),
                    $this->organizationName($visit),
                    $this->purpose($visit)
                )
            )
            ->warning()
            ->icon('heroicon-o-map-pin')
            ->actions([
                $this->authorizeHostVisitAction($visit),
                $this->rejectHostVisitAction($visit),
                $this->openVisitsAction(),
            ]);

        $this->sendSynchronously(
            $recipient,
            $notification
        );
    }

    private function sendSynchronously(
        User $recipient,
        Notification $notification
    ): void {
        $recipient->notifyNow(
            $notification->toDatabase()
        );

        DatabaseNotificationsSent::dispatch(
            $recipient
        );
    }

    private function recipient(VisitRecord $visit): ?User
    {
        $visit->loadMissing([
            'hostEmployee.user',
            'visitor',
            'organization',
        ]);

        $host = $visit->hostEmployee;

        if (
            ! $host instanceof EmployeeRecord
            || $host->status !== 'active'
            || $host->tenant_id !== $visit->tenant_id
        ) {
            return null;
        }

        return $host->user instanceof User
            ? $host->user
            : null;
    }

    private function visitorName(VisitRecord $visit): string
    {
        return $visit->visitor?->display_name
            ?: $visit->visitor?->full_name
            ?: 'um visitante';
    }

    private function organizationName(VisitRecord $visit): string
    {
        $organization = $visit->organization;

        return $organization?->display_name
            ?: $organization?->trade_name
            ?: $organization?->legal_name
            ?: 'unidade não identificada';
    }

    private function scheduledFor(VisitRecord $visit): string
    {
        return $visit->expected_start_at?->format(
            'd/m/Y \à\s H:i'
        ) ?? 'horário não informado';
    }

    private function purpose(VisitRecord $visit): string
    {
        return filled($visit->purpose)
            ? trim((string) $visit->purpose)
            : 'não informada';
    }

    private function authorizeHostVisitAction(
        VisitRecord $visit
    ): Action {
        return Action::make('authorizeHostVisit')
            ->label('Autorizar meu visitante')
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->url(
                $this->hostDecisionUrl(
                    $visit,
                    'authorizeHostVisit'
                )
            )
            ->markAsRead();
    }

    private function rejectHostVisitAction(
        VisitRecord $visit
    ): Action {
        return Action::make('rejectHostVisit')
            ->label('Não autorizar meu visitante')
            ->color('danger')
            ->icon('heroicon-o-x-circle')
            ->url(
                $this->hostDecisionUrl(
                    $visit,
                    'rejectHostVisit'
                )
            )
            ->markAsRead();
    }

    private function hostDecisionUrl(
        VisitRecord $visit,
        string $action
    ): string {
        return VisitRecordResource::getUrl(
            'list',
            [
                'tableAction' => $action,
                'tableActionRecord' => (string) $visit->getKey(),
            ]
        );
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
