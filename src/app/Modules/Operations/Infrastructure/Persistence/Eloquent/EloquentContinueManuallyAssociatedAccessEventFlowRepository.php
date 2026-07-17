<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation\ContinueManuallyAssociatedAccessEventFlowContext;
use App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation\ContinueManuallyAssociatedAccessEventFlowException;
use App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation\ContinueManuallyAssociatedAccessEventFlowRepository;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class EloquentContinueManuallyAssociatedAccessEventFlowRepository implements ContinueManuallyAssociatedAccessEventFlowRepository
{
    public function prepare(
        string $eventId,
        int $operatorUserId,
    ): ?ContinueManuallyAssociatedAccessEventFlowContext {
        return DB::transaction(
            function () use (
                $eventId,
                $operatorUserId,
            ): ?ContinueManuallyAssociatedAccessEventFlowContext {
                $event = AccessEventRecord::query()
                    ->lockForUpdate()
                    ->find($eventId);

                if (! $event instanceof AccessEventRecord) {
                    return null;
                }

                $operator = User::query()
                    ->lockForUpdate()
                    ->find($operatorUserId);

                if (! $operator instanceof User) {
                    throw new ContinueManuallyAssociatedAccessEventFlowException(
                        'O operador responsável não foi encontrado.'
                    );
                }

                try {
                    Gate::forUser($operator)->authorize(
                        'reprocessFlow',
                        $event
                    );
                } catch (AuthorizationException $exception) {
                    throw new ContinueManuallyAssociatedAccessEventFlowException(
                        message: 'O operador não possui autorização para continuar o fluxo deste evento.',
                        previous: $exception,
                    );
                }

                if (
                    $this->eventStatus($event)
                    !== AccessEventStatus::Processed
                    || trim((string) $event->result_code)
                        !== 'manual_association_completed'
                    || blank($event->visitor_id)
                    || blank($event->visit_id)
                ) {
                    throw new ContinueManuallyAssociatedAccessEventFlowException(
                        'Somente eventos processados por uma associação manual completa podem continuar por este fluxo.'
                    );
                }

                $association =
                    AccessEventManualAssociationRecord::query()
                        ->where(
                            'access_event_id',
                            $event->id
                        )
                        ->orderByDesc('associated_at')
                        ->orderByDesc('id')
                        ->lockForUpdate()
                        ->first();

                if (
                    ! $association
                    instanceof AccessEventManualAssociationRecord
                ) {
                    throw new ContinueManuallyAssociatedAccessEventFlowException(
                        'O histórico da associação manual completa não foi encontrado.'
                    );
                }

                if (
                    trim((string) $association->result_code)
                        !== 'manual_association_completed'
                    || $this->associationStatus($association)
                        !== AccessEventStatus::Processed
                    || (string) $association->selected_visitor_id
                        !== (string) $event->visitor_id
                    || (string) $association->selected_visit_id
                        !== (string) $event->visit_id
                    || (string) $association->tenant_id
                        !== (string) $event->tenant_id
                    || (string) $association->organization_id
                        !== (string) $event->organization_id
                ) {
                    throw new ContinueManuallyAssociatedAccessEventFlowException(
                        'O contexto atual do evento não corresponde à associação manual completa registrada.'
                    );
                }

                $visitor = VisitorRecord::query()
                    ->lockForUpdate()
                    ->find($event->visitor_id);

                if (! $visitor instanceof VisitorRecord) {
                    throw new ContinueManuallyAssociatedAccessEventFlowException(
                        'O visitante associado ao evento não foi encontrado.'
                    );
                }

                if (
                    $this->visitorStatus($visitor)
                    !== VisitorStatus::Active
                ) {
                    throw new ContinueManuallyAssociatedAccessEventFlowException(
                        'O visitante associado ao evento não está ativo.'
                    );
                }

                if (
                    (string) $visitor->tenant_id
                        !== (string) $event->tenant_id
                    || (string) $visitor->organization_id
                        !== (string) $event->organization_id
                ) {
                    throw new ContinueManuallyAssociatedAccessEventFlowException(
                        'O visitante associado não pertence ao grupo empresarial e à unidade do evento.'
                    );
                }

                $visit = VisitRecord::query()
                    ->lockForUpdate()
                    ->find($event->visit_id);

                if (! $visit instanceof VisitRecord) {
                    throw new ContinueManuallyAssociatedAccessEventFlowException(
                        'A visita associada ao evento não foi encontrada.'
                    );
                }

                if (
                    (string) $visit->tenant_id
                        !== (string) $event->tenant_id
                    || (string) $visit->organization_id
                        !== (string) $event->organization_id
                ) {
                    throw new ContinueManuallyAssociatedAccessEventFlowException(
                        'A visita associada não pertence ao grupo empresarial e à unidade do evento.'
                    );
                }

                if (
                    (string) $visit->visitor_id
                    !== (string) $visitor->id
                ) {
                    throw new ContinueManuallyAssociatedAccessEventFlowException(
                        'A visita associada não pertence ao visitante do evento.'
                    );
                }

                return new ContinueManuallyAssociatedAccessEventFlowContext(
                    eventId: (string) $event->id,
                    associationId: (string) $association->id,
                    visitorId: (string) $visitor->id,
                    visitId: (string) $visit->id,
                );
            }
        );
    }

    private function eventStatus(
        AccessEventRecord $event
    ): AccessEventStatus {
        $status = $event->status;

        if ($status instanceof AccessEventStatus) {
            return $status;
        }

        $status = AccessEventStatus::tryFrom(
            (string) $status
        );

        if (! $status instanceof AccessEventStatus) {
            throw new ContinueManuallyAssociatedAccessEventFlowException(
                'O evento possui uma situação inválida.'
            );
        }

        return $status;
    }

    private function associationStatus(
        AccessEventManualAssociationRecord $association
    ): AccessEventStatus {
        $status = $association->resulting_status;

        if ($status instanceof AccessEventStatus) {
            return $status;
        }

        $status = AccessEventStatus::tryFrom(
            (string) $status
        );

        if (! $status instanceof AccessEventStatus) {
            throw new ContinueManuallyAssociatedAccessEventFlowException(
                'O histórico da associação manual possui uma situação inválida.'
            );
        }

        return $status;
    }

    private function visitorStatus(
        VisitorRecord $visitor
    ): VisitorStatus {
        $status = $visitor->status;

        if ($status instanceof VisitorStatus) {
            return $status;
        }

        $status = VisitorStatus::tryFrom(
            (string) $status
        );

        if (! $status instanceof VisitorStatus) {
            throw new ContinueManuallyAssociatedAccessEventFlowException(
                'O visitante associado possui uma situação inválida.'
            );
        }

        return $status;
    }
}
