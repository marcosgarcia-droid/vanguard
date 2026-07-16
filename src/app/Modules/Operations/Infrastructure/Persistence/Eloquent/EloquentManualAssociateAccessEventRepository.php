<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Operations\Application\AccessControl\Events\ManualAssociate\ManualAssociateAccessEventException;
use App\Modules\Operations\Application\AccessControl\Events\ManualAssociate\ManualAssociateAccessEventRepository;
use App\Modules\Operations\Application\AccessControl\Events\ManualAssociate\ManualAssociateAccessEventResult;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentManualAssociateAccessEventRepository implements ManualAssociateAccessEventRepository
{
    public function associate(
        string $eventId,
        string $visitorId,
        ?string $visitId,
        int $operatorUserId,
        string $reason,
        string $idempotencyKey,
    ): ?ManualAssociateAccessEventResult {
        try {
            return DB::transaction(
                fn (): ?ManualAssociateAccessEventResult => $this
                    ->associateWithinTransaction(
                        eventId: $eventId,
                        visitorId: $visitorId,
                        visitId: $visitId,
                        operatorUserId: $operatorUserId,
                        reason: $reason,
                        idempotencyKey: $idempotencyKey,
                    )
            );
        } catch (QueryException $exception) {
            $existing = AccessEventManualAssociationRecord::query()
                ->where(
                    'idempotency_key',
                    $idempotencyKey
                )
                ->first();

            if (
                $existing
                instanceof AccessEventManualAssociationRecord
            ) {
                return $this->duplicateResult(
                    association: $existing,
                    eventId: $eventId,
                    visitorId: $visitorId,
                    visitId: $visitId,
                    operatorUserId: $operatorUserId,
                    reason: $reason,
                );
            }

            throw $exception;
        }
    }

    private function associateWithinTransaction(
        string $eventId,
        string $visitorId,
        ?string $visitId,
        int $operatorUserId,
        string $reason,
        string $idempotencyKey,
    ): ?ManualAssociateAccessEventResult {
        $existing = AccessEventManualAssociationRecord::query()
            ->where(
                'idempotency_key',
                $idempotencyKey
            )
            ->lockForUpdate()
            ->first();

        if (
            $existing
            instanceof AccessEventManualAssociationRecord
        ) {
            return $this->duplicateResult(
                association: $existing,
                eventId: $eventId,
                visitorId: $visitorId,
                visitId: $visitId,
                operatorUserId: $operatorUserId,
                reason: $reason,
            );
        }

        $event = AccessEventRecord::query()
            ->lockForUpdate()
            ->find($eventId);

        if (! $event instanceof AccessEventRecord) {
            return null;
        }

        if (
            $this->eventStatus($event)
            !== AccessEventStatus::PendingAssociation
        ) {
            throw new ManualAssociateAccessEventException(
                'Somente eventos aguardando associação podem ser associados manualmente.'
            );
        }

        $operator = User::query()
            ->lockForUpdate()
            ->find($operatorUserId);

        if (! $operator instanceof User) {
            throw new ManualAssociateAccessEventException(
                'O operador responsável não foi encontrado.'
            );
        }

        $visitor = VisitorRecord::withTrashed()
            ->lockForUpdate()
            ->find($visitorId);

        if (! $visitor instanceof VisitorRecord) {
            throw new ManualAssociateAccessEventException(
                'O visitante selecionado não foi encontrado.'
            );
        }

        if (
            $visitor->trashed()
            || $visitor->status !== VisitorStatus::Active
        ) {
            throw new ManualAssociateAccessEventException(
                'O visitante selecionado não está ativo.'
            );
        }

        if (
            (string) $visitor->tenant_id
                !== (string) $event->tenant_id
            || (string) $visitor->organization_id
                !== (string) $event->organization_id
        ) {
            throw new ManualAssociateAccessEventException(
                'O visitante selecionado não pertence ao grupo empresarial e à unidade do evento.'
            );
        }

        $visit = null;

        if ($visitId !== null) {
            $visit = VisitRecord::withTrashed()
                ->lockForUpdate()
                ->find($visitId);

            if (
                ! $visit instanceof VisitRecord
                || $visit->trashed()
            ) {
                throw new ManualAssociateAccessEventException(
                    'A visita selecionada não foi encontrada.'
                );
            }

            if (
                (string) $visit->tenant_id
                    !== (string) $event->tenant_id
                || (string) $visit->organization_id
                    !== (string) $event->organization_id
            ) {
                throw new ManualAssociateAccessEventException(
                    'A visita selecionada não pertence ao grupo empresarial e à unidade do evento.'
                );
            }

            if (
                (string) $visit->visitor_id
                !== (string) $visitor->id
            ) {
                throw new ManualAssociateAccessEventException(
                    'A visita selecionada não pertence ao visitante informado.'
                );
            }

            $expectedStatus = $this->eligibleVisitStatus(
                $this->eventDirection($event)
            );

            if (
                $this->visitStatus($visit)
                !== $expectedStatus
            ) {
                throw new ManualAssociateAccessEventException(
                    sprintf(
                        'A visita selecionada deve estar com a situação “%s” para este evento.',
                        $expectedStatus->label()
                    )
                );
            }
        }

        $previousVisitor = filled($event->visitor_id)
            ? VisitorRecord::withTrashed()->find(
                $event->visitor_id
            )
            : null;

        $previousVisit = filled($event->visit_id)
            ? VisitRecord::withTrashed()->find(
                $event->visit_id
            )
            : null;

        $associatedAt = now();

        $resultingStatus = $visit === null
            ? AccessEventStatus::PendingAssociation
            : AccessEventStatus::Processed;

        $resultCode = $visit === null
            ? 'manual_visitor_association_pending_visit'
            : 'manual_association_completed';

        $resultMessage = $visit === null
            ? 'Visitante associado manualmente; o evento permanece aguardando uma visita compatível.'
            : 'Evento associado manualmente ao visitante e à visita sem alterar a situação operacional da visita.';

        $association =
            AccessEventManualAssociationRecord::query()
                ->create([
                    'access_event_id' => $event->id,
                    'tenant_id' => $event->tenant_id,
                    'organization_id' => $event->organization_id,
                    'idempotency_key' => $idempotencyKey,
                    'previous_visitor_id' => $event->visitor_id,
                    'previous_visit_id' => $event->visit_id,
                    'selected_visitor_id' => $visitor->id,
                    'selected_visit_id' => $visit?->id,
                    'operator_user_id' => $operator->id,
                    'operator_name' => $this->operatorName(
                        $operator
                    ),
                    'previous_visitor_name' => $this
                        ->visitorName($previousVisitor),
                    'previous_visit_reference' => $this
                        ->visitReference($previousVisit),
                    'selected_visitor_name' => $this
                        ->visitorName($visitor),
                    'selected_visit_reference' => $this
                        ->visitReference($visit),
                    'reason' => $reason,
                    'resulting_status' => $resultingStatus,
                    'result_code' => $resultCode,
                    'result_message' => $resultMessage,
                    'associated_at' => $associatedAt,
                ]);

        $event
            ->forceFill([
                'visitor_id' => $visitor->id,
                'visit_id' => $visit?->id,
                'status' => $resultingStatus,
                'result_code' => $resultCode,
                'result_message' => $resultMessage,
                'processed_at' => $visit === null
                    ? null
                    : $associatedAt,
            ])
            ->saveQuietly();

        return new ManualAssociateAccessEventResult(
            eventId: $event->id,
            associationId: $association->id,
            status: $resultingStatus,
            visitorId: $visitor->id,
            visitId: $visit?->id,
            resultCode: $resultCode,
            duplicate: false,
        );
    }

    private function duplicateResult(
        AccessEventManualAssociationRecord $association,
        string $eventId,
        string $visitorId,
        ?string $visitId,
        int $operatorUserId,
        string $reason,
    ): ManualAssociateAccessEventResult {
        if (
            (string) $association->access_event_id
                !== $eventId
            || (string) $association->selected_visitor_id
                !== $visitorId
            || $this->nullableString(
                $association->selected_visit_id
            ) !== $visitId
            || (int) $association->operator_user_id
                !== $operatorUserId
            || trim((string) $association->reason)
                !== $reason
        ) {
            throw new ManualAssociateAccessEventException(
                'A chave de idempotência já foi utilizada em outra associação manual.'
            );
        }

        return new ManualAssociateAccessEventResult(
            eventId: (string) $association->access_event_id,
            associationId: $association->id,
            status: $this->associationStatus(
                $association
            ),
            visitorId: (string) $association->selected_visitor_id,
            visitId: $this->nullableString(
                $association->selected_visit_id
            ),
            resultCode: (string) $association->result_code,
            duplicate: true,
        );
    }

    private function eventStatus(
        AccessEventRecord $event
    ): AccessEventStatus {
        if ($event->status instanceof AccessEventStatus) {
            return $event->status;
        }

        return AccessEventStatus::tryFrom(
            (string) $event->status
        ) ?? throw new RuntimeException(
            'O evento possui um status inválido.'
        );
    }

    private function eventDirection(
        AccessEventRecord $event
    ): AccessEventDirection {
        if (
            $event->direction
            instanceof AccessEventDirection
        ) {
            return $event->direction;
        }

        return AccessEventDirection::tryFrom(
            (string) $event->direction
        ) ?? throw new RuntimeException(
            'O evento possui uma direção inválida.'
        );
    }

    private function visitStatus(
        VisitRecord $visit
    ): VisitStatus {
        if ($visit->status instanceof VisitStatus) {
            return $visit->status;
        }

        return VisitStatus::tryFrom(
            (string) $visit->status
        ) ?? throw new RuntimeException(
            'A visita possui uma situação inválida.'
        );
    }

    private function associationStatus(
        AccessEventManualAssociationRecord $association
    ): AccessEventStatus {
        if (
            $association->resulting_status
            instanceof AccessEventStatus
        ) {
            return $association->resulting_status;
        }

        return AccessEventStatus::tryFrom(
            (string) $association->resulting_status
        ) ?? throw new RuntimeException(
            'A associação possui um status resultante inválido.'
        );
    }

    private function eligibleVisitStatus(
        AccessEventDirection $direction
    ): VisitStatus {
        return match ($direction) {
            AccessEventDirection::Entry => VisitStatus::Authorized,
            AccessEventDirection::Exit => VisitStatus::InProgress,
        };
    }

    private function visitorName(
        ?VisitorRecord $visitor
    ): ?string {
        if (! $visitor instanceof VisitorRecord) {
            return null;
        }

        $name = trim(
            (string) (
                $visitor->display_name
                ?: $visitor->full_name
            )
        );

        return $name !== ''
            ? mb_substr($name, 0, 255)
            : null;
    }

    private function visitReference(
        ?VisitRecord $visit
    ): ?string {
        if (! $visit instanceof VisitRecord) {
            return null;
        }

        $reference = collect([
            trim((string) $visit->purpose),
            $visit->expected_start_at?->format(
                'd/m/Y H:i'
            ),
        ])
            ->filter()
            ->implode(' - ');

        return $reference !== ''
            ? mb_substr($reference, 0, 255)
            : null;
    }

    private function operatorName(User $operator): string
    {
        $name = trim((string) $operator->name);

        if ($name === '') {
            $name = trim((string) $operator->email);
        }

        return mb_substr(
            $name !== ''
                ? $name
                : "Usuário {$operator->id}",
            0,
            255
        );
    }

    private function nullableString(
        mixed $value
    ): ?string {
        $value = trim((string) $value);

        return $value !== ''
            ? $value
            : null;
    }
}
