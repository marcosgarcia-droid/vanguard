<?php

namespace App\Modules\Operations\UI\Notifications;

use App\Models\User;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\VisitRecordResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class VisitGatehouseDecisionNotifier
{
    private const GATEHOUSE_PERMISSION =
        'OperateGatehouse:VisitRecord';

    public function notifyAuthorizedByHost(
        VisitRecord $visit,
        int $decidedByUserId
    ): void {
        $visit->loadMissing([
            'visitor',
            'organization',
            'hostEmployee',
        ]);

        $recipients = $this->recipients(
            $visit,
            $decidedByUserId
        );

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Visita autorizada pelo visitado')
            ->body(
                sprintf(
                    '%s autorizou a visita de %s na unidade %s.',
                    $this->hostName($visit),
                    $this->visitorName($visit),
                    $this->organizationName($visit)
                )
            )
            ->success()
            ->icon('heroicon-o-check-circle')
            ->actions([
                $this->openVisitAction($visit),
            ])
            ->sendToDatabase(
                $recipients,
                isEventDispatched: true
            );
    }

    public function notifyRejectedByHost(
        VisitRecord $visit,
        int $decidedByUserId
    ): void {
        $visit->loadMissing([
            'visitor',
            'organization',
            'hostEmployee',
        ]);

        $recipients = $this->recipients(
            $visit,
            $decidedByUserId
        );

        if ($recipients->isEmpty()) {
            return;
        }

        $body = sprintf(
            '%s não autorizou a visita de %s na unidade %s.',
            $this->hostName($visit),
            $this->visitorName($visit),
            $this->organizationName($visit)
        );

        if (filled($visit->rejection_reason)) {
            $body .= ' Motivo: '.Str::limit(
                trim((string) $visit->rejection_reason),
                240
            );
        }

        Notification::make()
            ->title('Visita não autorizada pelo visitado')
            ->body($body)
            ->danger()
            ->icon('heroicon-o-x-circle')
            ->actions([
                $this->openVisitAction($visit),
            ])
            ->sendToDatabase(
                $recipients,
                isEventDispatched: true
            );
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(
        VisitRecord $visit,
        int $excludedUserId
    ): Collection {
        $permissionUsers = User::permission(
            self::GATEHOUSE_PERMISSION
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
                fn (User $user): bool => $user->id
                    === $excludedUserId
            )
            ->filter(
                fn (User $user): bool => Gate::forUser(
                    $user
                )->allows(
                    'operateGatehouse',
                    $visit
                )
            )
            ->values();
    }

    private function visitorName(
        VisitRecord $visit
    ): string {
        return $visit->visitor?->display_name
            ?: $visit->visitor?->full_name
            ?: 'visitante não identificado';
    }

    private function hostName(
        VisitRecord $visit
    ): string {
        return $visit->hostEmployee?->full_name
            ?: 'O funcionário visitado';
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
                        'tableActionRecord' => (string) $visit
                            ->getKey(),
                    ]
                )
            )
            ->markAsRead();
    }
}
