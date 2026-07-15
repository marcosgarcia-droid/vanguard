<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Operations\Application\AccessControl\Events\Decide\DecideAccessEventRepository;
use App\Modules\Operations\Application\AccessControl\Events\Decide\DecideAccessEventResult;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentDecideAccessEventRepository implements DecideAccessEventRepository
{
    private const MAX_VERSION = 65535;

    public function decide(
        string $eventId,
        bool $automaticExecutionAllowed,
    ): ?DecideAccessEventResult {
        return DB::transaction(
            function () use (
                $eventId,
                $automaticExecutionAllowed
            ): ?DecideAccessEventResult {
                $event = AccessEventRecord::query()
                    ->lockForUpdate()
                    ->find($eventId);

                if (
                    ! $event
                    instanceof AccessEventRecord
                ) {
                    return null;
                }

                $this->loadLockedAssociations(
                    $event
                );

                $derived = $this->derive(
                    $event
                );

                $automaticExecutionEnabled =
                    $derived['decision']->isCandidate()
                    && $automaticExecutionAllowed;

                $latest =
                    AccessEventOperationalDecisionRecord::query()
                        ->where(
                            'access_event_id',
                            $event->id
                        )
                        ->orderByDesc('version')
                        ->first();

                if (
                    $latest
                    instanceof AccessEventOperationalDecisionRecord
                    && $this->isSameDecision(
                        latest: $latest,
                        event: $event,
                        decision: $derived['decision'],
                        reasonCode: $derived['reason_code'],
                        reasonMessage: $derived['reason_message'],
                        automaticExecutionEnabled: $automaticExecutionEnabled,
                    )
                ) {
                    return $this->result(
                        record: $latest,
                        duplicate: true,
                    );
                }

                $version =
                    ((int) ($latest?->version ?? 0)) + 1;

                if ($version > self::MAX_VERSION) {
                    throw new RuntimeException(
                        'O limite de versões da decisão operacional foi atingido.'
                    );
                }

                $record =
                    AccessEventOperationalDecisionRecord::query()
                        ->create([
                            'access_event_id' => $event->id,
                            'tenant_id' => $event->tenant_id,
                            'organization_id' => $event->organization_id,
                            'visitor_id' => $event->visitor_id,
                            'visit_id' => $event->visit_id,
                            'version' => $version,
                            'decision' => $derived['decision'],
                            'reason_code' => $derived['reason_code'],
                            'reason_message' => $derived['reason_message'],
                            'automatic_execution_enabled' => $automaticExecutionEnabled,
                            'decided_at' => now(),
                        ]);

                return $this->result(
                    record: $record,
                    duplicate: false,
                );
            }
        );
    }

    private function loadLockedAssociations(
        AccessEventRecord $event
    ): void {
        $visitor = null;

        if (filled($event->visitor_id)) {
            $visitor = VisitorRecord::query()
                ->lockForUpdate()
                ->find(
                    $event->visitor_id
                );
        }

        $visit = null;

        if (filled($event->visit_id)) {
            $visit = VisitRecord::query()
                ->lockForUpdate()
                ->find(
                    $event->visit_id
                );
        }

        $event->setRelation(
            'visitor',
            $visitor
        );

        $event->setRelation(
            'visit',
            $visit
        );
    }

    /**
     * @return array{
     *     decision: AccessEventOperationalDecision,
     *     reason_code: string,
     *     reason_message: string
     * }
     */
    private function derive(
        AccessEventRecord $event
    ): array {
        $eventStatus = $this->eventStatus(
            $event
        );

        if ($eventStatus === AccessEventStatus::Ignored) {
            return $this->draft(
                decision: AccessEventOperationalDecision::NoAction,
                reasonCode: 'event_ignored',
                reasonMessage: 'O evento foi ignorado no processamento técnico e não requer ação operacional.',
            );
        }

        if ($eventStatus !== AccessEventStatus::Processed) {
            return $this->draft(
                decision: AccessEventOperationalDecision::ManualReview,
                reasonCode: 'event_not_processed',
                reasonMessage: 'O evento ainda não concluiu a associação técnica e precisa de revisão antes de qualquer operação.',
            );
        }

        if (
            blank($event->visitor_id)
            || blank($event->visit_id)
            || ! $event->visitor
            instanceof VisitorRecord
            || ! $event->visit
            instanceof VisitRecord
        ) {
            return $this->draft(
                decision: AccessEventOperationalDecision::ManualReview,
                reasonCode: 'incomplete_association',
                reasonMessage: 'O evento processado não possui visitante e visita válidos associados.',
            );
        }

        if (
            $event->visitor->tenant_id
                !== $event->tenant_id
            || $event->visitor->organization_id
                !== $event->organization_id
            || $event->visit->tenant_id
                !== $event->tenant_id
            || $event->visit->organization_id
                !== $event->organization_id
        ) {
            return $this->draft(
                decision: AccessEventOperationalDecision::ManualReview,
                reasonCode: 'association_scope_mismatch',
                reasonMessage: 'A associação do evento não pertence ao mesmo grupo empresarial e unidade.',
            );
        }

        if (
            $event->visit->visitor_id
            !== $event->visitor_id
        ) {
            return $this->draft(
                decision: AccessEventOperationalDecision::ManualReview,
                reasonCode: 'visit_visitor_mismatch',
                reasonMessage: 'A visita associada pertence a outro visitante.',
            );
        }

        if (
            $event->visitor->status
            !== VisitorStatus::Active
        ) {
            return $this->draft(
                decision: AccessEventOperationalDecision::ManualReview,
                reasonCode: 'visitor_inactive',
                reasonMessage: 'O visitante associado está inativo e requer avaliação manual.',
            );
        }

        if (
            $event->direction === AccessEventDirection::Entry
            && blank($event->visitor->photo_path)
        ) {
            return $this->draft(
                decision: AccessEventOperationalDecision::ManualReview,
                reasonCode: 'visitor_photo_missing',
                reasonMessage: 'O visitante não possui foto facial local para uma futura operação de entrada.',
            );
        }

        return match ($event->direction) {
            AccessEventDirection::Entry => $this->deriveEntryDecision(
                $event->visit
            ),

            AccessEventDirection::Exit => $this->deriveExitDecision(
                $event->visit
            ),
        };
    }

    /**
     * @return array{
     *     decision: AccessEventOperationalDecision,
     *     reason_code: string,
     *     reason_message: string
     * }
     */
    private function deriveEntryDecision(
        VisitRecord $visit
    ): array {
        return match ($visit->status) {
            VisitStatus::Authorized => $this->draft(
                decision: AccessEventOperationalDecision::CheckInCandidate,
                reasonCode: 'check_in_candidate',
                reasonMessage: 'O evento de entrada está associado a uma visita autorizada.',
            ),

            VisitStatus::InProgress => $this->draft(
                decision: AccessEventOperationalDecision::NoAction,
                reasonCode: 'visit_already_in_progress',
                reasonMessage: 'A visita já está em andamento e não requer nova entrada.',
            ),

            VisitStatus::Completed => $this->draft(
                decision: AccessEventOperationalDecision::NoAction,
                reasonCode: 'visit_already_completed',
                reasonMessage: 'A visita já foi concluída e não requer operação adicional.',
            ),

            default => $this->draft(
                decision: AccessEventOperationalDecision::ManualReview,
                reasonCode: 'visit_not_eligible_for_entry',
                reasonMessage: 'O status atual da visita não permite derivar uma entrada automática.',
            ),
        };
    }

    /**
     * @return array{
     *     decision: AccessEventOperationalDecision,
     *     reason_code: string,
     *     reason_message: string
     * }
     */
    private function deriveExitDecision(
        VisitRecord $visit
    ): array {
        return match ($visit->status) {
            VisitStatus::InProgress => $this->draft(
                decision: AccessEventOperationalDecision::CheckOutCandidate,
                reasonCode: 'check_out_candidate',
                reasonMessage: 'O evento de saída está associado a uma visita em andamento.',
            ),

            VisitStatus::Completed => $this->draft(
                decision: AccessEventOperationalDecision::NoAction,
                reasonCode: 'visit_already_completed',
                reasonMessage: 'A visita já foi concluída e não requer nova saída.',
            ),

            VisitStatus::Authorized => $this->draft(
                decision: AccessEventOperationalDecision::ManualReview,
                reasonCode: 'visit_not_in_progress',
                reasonMessage: 'A visita está autorizada, mas ainda não possui entrada registrada.',
            ),

            default => $this->draft(
                decision: AccessEventOperationalDecision::ManualReview,
                reasonCode: 'visit_not_eligible_for_exit',
                reasonMessage: 'O status atual da visita não permite derivar uma saída automática.',
            ),
        };
    }

    /**
     * @return array{
     *     decision: AccessEventOperationalDecision,
     *     reason_code: string,
     *     reason_message: string
     * }
     */
    private function draft(
        AccessEventOperationalDecision $decision,
        string $reasonCode,
        string $reasonMessage,
    ): array {
        return [
            'decision' => $decision,
            'reason_code' => $reasonCode,
            'reason_message' => $reasonMessage,
        ];
    }

    private function eventStatus(
        AccessEventRecord $event
    ): AccessEventStatus {
        if (
            $event->status
            instanceof AccessEventStatus
        ) {
            return $event->status;
        }

        $status = AccessEventStatus::tryFrom(
            (string) $event->status
        );

        if (
            ! $status
            instanceof AccessEventStatus
        ) {
            throw new RuntimeException(
                'O evento possui um status inválido.'
            );
        }

        return $status;
    }

    private function isSameDecision(
        AccessEventOperationalDecisionRecord $latest,
        AccessEventRecord $event,
        AccessEventOperationalDecision $decision,
        string $reasonCode,
        string $reasonMessage,
        bool $automaticExecutionEnabled,
    ): bool {
        return $latest->decision === $decision
            && $latest->reason_code === $reasonCode
            && $latest->reason_message === $reasonMessage
            && $latest->visitor_id === $event->visitor_id
            && $latest->visit_id === $event->visit_id
            && $latest->automatic_execution_enabled
                === $automaticExecutionEnabled;
    }

    private function result(
        AccessEventOperationalDecisionRecord $record,
        bool $duplicate,
    ): DecideAccessEventResult {
        return new DecideAccessEventResult(
            decisionId: $record->id,
            eventId: $record->access_event_id,
            version: (int) $record->version,
            decision: $record->decision,
            reasonCode: $record->reason_code,
            automaticExecutionEnabled: $record->automatic_execution_enabled,
            duplicate: $duplicate,
        );
    }
}
