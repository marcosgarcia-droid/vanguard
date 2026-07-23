<?php

namespace App\Modules\Operations\UI\Notifications;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\VisitRecordResource;
use Filament\Actions\Action;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Notifications\DatabaseNotification as DatabaseNotificationModel;
use Illuminate\Support\Str;

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
            ->viewData(
                $this->visitMetadata($visit)
            )
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
            ->viewData(
                $this->visitMetadata($visit)
            )
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

    public function notifyCancelled(
        VisitRecord $visit,
        int $cancelledByUserId
    ): void {
        $recipient = $this->recipient($visit);

        if (
            ! $recipient instanceof User
            || (int) $recipient->id === $cancelledByUserId
            || $this->hasCancellationNotification(
                $recipient,
                $visit
            )
        ) {
            return;
        }

        $notification = Notification::make()
            ->title('Visita cancelada')
            ->body(
                sprintf(
                    'A visita de %s na unidade %s foi cancelada. Motivo: %s',
                    $this->visitorName($visit),
                    $this->organizationName($visit),
                    $this->cancellationReason($visit)
                )
            )
            ->danger()
            ->icon('heroicon-o-no-symbol')
            ->viewData(
                $this->cancellationMetadata($visit)
            )
            ->actions([
                $this->openVisitAction($visit),
            ]);

        $this->sendSynchronously(
            $recipient,
            $notification
        );
    }

    public function closeDecisionActions(
        VisitRecord $visit
    ): void {
        $recipient = $this->recipient($visit);

        if (! $recipient instanceof User) {
            return;
        }

        $wasUpdated = false;

        $recipient->notifications()
            ->get()
            ->each(
                function (
                    DatabaseNotificationModel $notification
                ) use (
                    $visit,
                    &$wasUpdated
                ): void {
                    $data = $notification->data;

                    if (
                        ! is_array($data)
                        || ! $this->belongsToVisit(
                            $data,
                            $visit
                        )
                    ) {
                        return;
                    }

                    $viewData = is_array(
                        $data['viewData'] ?? null
                    )
                        ? $data['viewData']
                        : [];

                    $hasDecisionActions = $this
                        ->hasDecisionActions($data);

                    $isHostDecisionNotification = (
                        $viewData['notification_kind']
                        ?? null
                    ) === 'visit_host_decision'
                        || $hasDecisionActions;

                    if (! $isHostDecisionNotification) {
                        return;
                    }

                    $decisionStatus = $visit->status->value;

                    $statusChanged = (
                        $viewData['decision_status']
                        ?? null
                    ) !== $decisionStatus;

                    if (
                        ! $hasDecisionActions
                        && ! $statusChanged
                    ) {
                        return;
                    }

                    if ($hasDecisionActions) {
                        $data['actions'] = [
                            $this->openVisitAction(
                                $visit
                            )->toArray(),
                        ];
                    }

                    $data['viewData'] = [
                        ...$viewData,
                        ...$this->visitMetadata($visit),
                        'decision_status' => $decisionStatus,
                    ];

                    $notification->forceFill([
                        'data' => $data,
                    ])->save();

                    $wasUpdated = true;
                }
            );

        if ($wasUpdated) {
            DatabaseNotificationsSent::dispatch(
                $recipient
            );
        }
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

    /**
     * @return array<string, string>
     */
    private function cancellationMetadata(
        VisitRecord $visit
    ): array {
        return [
            'notification_kind' => 'visit_cancelled',
            'visit_id' => (string) $visit->getKey(),
            'decision_status' => $visit->status->value,
        ];
    }

    private function hasCancellationNotification(
        User $recipient,
        VisitRecord $visit
    ): bool {
        $visitId = (string) $visit->getKey();

        return $recipient->notifications()
            ->get()
            ->contains(
                function (
                    DatabaseNotificationModel $notification
                ) use ($visitId): bool {
                    $data = $notification->data;

                    if (! is_array($data)) {
                        return false;
                    }

                    $viewData = is_array(
                        $data['viewData'] ?? null
                    )
                        ? $data['viewData']
                        : [];

                    return (
                        $viewData['notification_kind']
                        ?? null
                    ) === 'visit_cancelled'
                        && (string) (
                            $viewData['visit_id']
                            ?? ''
                        ) === $visitId;
                }
            );
    }

    private function cancellationReason(
        VisitRecord $visit
    ): string {
        if (blank($visit->cancellation_reason)) {
            return 'não informado.';
        }

        $reason = Str::limit(
            trim(
                (string) $visit->cancellation_reason
            ),
            240
        );

        return Str::endsWith(
            $reason,
            [
                '.',
                '!',
                '?',
            ]
        )
            ? $reason
            : $reason.'.';
    }

    /**
     * @return array<string, string>
     */
    private function visitMetadata(
        VisitRecord $visit
    ): array {
        return [
            'notification_kind' => 'visit_host_decision',
            'visit_id' => (string) $visit->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function belongsToVisit(
        array $data,
        VisitRecord $visit
    ): bool {
        $visitId = (string) $visit->getKey();

        $viewData = is_array(
            $data['viewData'] ?? null
        )
            ? $data['viewData']
            : [];

        if (
            (string) (
                $viewData['visit_id']
                ?? ''
            ) === $visitId
        ) {
            return true;
        }

        foreach ($data['actions'] ?? [] as $action) {
            if (! is_array($action)) {
                continue;
            }

            $url = $action['url'] ?? null;

            if (! is_string($url) || blank($url)) {
                continue;
            }

            $query = parse_url(
                html_entity_decode($url),
                PHP_URL_QUERY
            );

            if (! is_string($query)) {
                continue;
            }

            parse_str($query, $parameters);

            if (
                (string) (
                    $parameters['tableActionRecord']
                    ?? ''
                ) === $visitId
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasDecisionActions(
        array $data
    ): bool {
        foreach ($data['actions'] ?? [] as $action) {
            if (
                is_array($action)
                && in_array(
                    $action['name'] ?? null,
                    [
                        'authorizeHostVisit',
                        'rejectHostVisit',
                    ],
                    true
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function openVisitAction(
        VisitRecord $visit
    ): Action {
        return Action::make('openVisit')
            ->label('Visualizar visita')
            ->url(
                VisitRecordResource::getUrl(
                    'list',
                    [
                        'tableAction' => 'view',
                        'tableActionRecord' => (string) $visit->getKey(),
                    ]
                )
            )
            ->markAsRead();
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
