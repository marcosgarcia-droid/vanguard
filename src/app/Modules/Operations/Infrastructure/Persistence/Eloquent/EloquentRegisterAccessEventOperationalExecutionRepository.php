<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Operations\Application\AccessControl\Events\Execute\RegisterAccessEventOperationalExecutionRepository;
use App\Modules\Operations\Application\AccessControl\Events\Execute\RegisterAccessEventOperationalExecutionResult;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionSource;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentRegisterAccessEventOperationalExecutionRepository implements RegisterAccessEventOperationalExecutionRepository
{
    private const MAX_ATTEMPTS = 65535;

    public function registerAutomaticAttempt(
        string $decisionId,
        bool $automaticExecutionAllowed,
    ): ?RegisterAccessEventOperationalExecutionResult {
        return DB::transaction(
            function () use (
                $decisionId,
                $automaticExecutionAllowed
            ): ?RegisterAccessEventOperationalExecutionResult {
                $decision =
                    AccessEventOperationalDecisionRecord::query()
                        ->lockForUpdate()
                        ->find($decisionId);

                if (
                    ! $decision
                    instanceof AccessEventOperationalDecisionRecord
                ) {
                    return null;
                }

                $event = AccessEventRecord::query()
                    ->lockForUpdate()
                    ->find(
                        $decision->access_event_id
                    );

                if (! $event instanceof AccessEventRecord) {
                    throw new RuntimeException(
                        'O evento relacionado à decisão operacional não foi encontrado.'
                    );
                }

                $visitor = null;

                if (filled($decision->visitor_id)) {
                    $visitor = VisitorRecord::query()
                        ->lockForUpdate()
                        ->find(
                            $decision->visitor_id
                        );
                }

                $visit = null;

                if (filled($decision->visit_id)) {
                    $visit = VisitRecord::query()
                        ->lockForUpdate()
                        ->find(
                            $decision->visit_id
                        );
                }

                $latestDecision =
                    AccessEventOperationalDecisionRecord::query()
                        ->where(
                            'access_event_id',
                            $decision->access_event_id
                        )
                        ->orderByDesc('version')
                        ->lockForUpdate()
                        ->first();

                $outcome = $this->deriveOutcome(
                    decision: $decision,
                    latestDecision: $latestDecision,
                    event: $event,
                    visitor: $visitor,
                    visit: $visit,
                    automaticExecutionAllowed: $automaticExecutionAllowed,
                );

                $visitStatus = $this->visitStatusValue(
                    $visit
                );

                $latestAttempt =
                    AccessEventOperationalExecutionRecord::query()
                        ->where(
                            'operational_decision_id',
                            $decision->id
                        )
                        ->orderByDesc('attempt_number')
                        ->lockForUpdate()
                        ->first();

                if (
                    $latestAttempt
                    instanceof AccessEventOperationalExecutionRecord
                    && $this->isSameAttempt(
                        attempt: $latestAttempt,
                        outcome: $outcome,
                        automaticExecutionAllowed: $automaticExecutionAllowed,
                        visitStatus: $visitStatus,
                    )
                ) {
                    return $this->result(
                        record: $latestAttempt,
                        duplicate: true,
                    );
                }

                $attemptNumber =
                    ((int) (
                        $latestAttempt?->attempt_number ?? 0
                    )) + 1;

                if (
                    $attemptNumber
                    > self::MAX_ATTEMPTS
                ) {
                    throw new RuntimeException(
                        'O limite de tentativas da decisão operacional foi atingido.'
                    );
                }

                $completedAt =
                    $outcome['status']->isFinal()
                        ? now()
                        : null;

                $record =
                    AccessEventOperationalExecutionRecord::query()
                        ->create([
                            'operational_decision_id' => $decision->id,
                            'access_event_id' => $decision->access_event_id,
                            'tenant_id' => $decision->tenant_id,
                            'organization_id' => $decision->organization_id,
                            'visitor_id' => $decision->visitor_id,
                            'visit_id' => $decision->visit_id,
                            'operator_user_id' => null,
                            'attempt_number' => $attemptNumber,
                            'source' => AccessEventOperationalExecutionSource::Automatic,
                            'status' => $outcome['status'],
                            'reason_code' => $outcome['reason_code'],
                            'reason_message' => $outcome['reason_message'],
                            'automatic_execution_allowed' => $automaticExecutionAllowed,
                            'visit_status_before' => $visitStatus,
                            'visit_status_after' => $visitStatus,
                            'attempted_at' => now(),
                            'completed_at' => $completedAt,
                        ]);

                return $this->result(
                    record: $record,
                    duplicate: false,
                );
            }
        );
    }

    /**
     * @return array{
     *     status: AccessEventOperationalExecutionStatus,
     *     reason_code: string,
     *     reason_message: string
     * }
     */
    private function deriveOutcome(
        AccessEventOperationalDecisionRecord $decision,
        ?AccessEventOperationalDecisionRecord $latestDecision,
        AccessEventRecord $event,
        ?VisitorRecord $visitor,
        ?VisitRecord $visit,
        bool $automaticExecutionAllowed,
    ): array {
        if (
            ! $latestDecision
            instanceof AccessEventOperationalDecisionRecord
            || $latestDecision->id !== $decision->id
        ) {
            return $this->outcome(
                status: AccessEventOperationalExecutionStatus::Blocked,
                reasonCode: 'stale_operational_decision',
                reasonMessage: 'A decisão não é mais a versão operacional mais recente do evento.',
            );
        }

        if (! $decision->decision->isCandidate()) {
            return $this->outcome(
                status: AccessEventOperationalExecutionStatus::Skipped,
                reasonCode: 'decision_not_executable',
                reasonMessage: 'A decisão operacional não representa uma operação de entrada ou saída.',
            );
        }

        if (! $automaticExecutionAllowed) {
            return $this->outcome(
                status: AccessEventOperationalExecutionStatus::Blocked,
                reasonCode: 'automatic_execution_disabled',
                reasonMessage: 'A execução automática está desativada pelo modo operacional ou pela feature flag.',
            );
        }

        if (! $decision->automatic_execution_enabled) {
            return $this->outcome(
                status: AccessEventOperationalExecutionStatus::Blocked,
                reasonCode: 'decision_automatic_execution_not_authorized',
                reasonMessage: 'A decisão não foi criada com autorização para execução automática.',
            );
        }

        if (
            $event->tenant_id !== $decision->tenant_id
            || $event->organization_id
                !== $decision->organization_id
        ) {
            return $this->outcome(
                status: AccessEventOperationalExecutionStatus::Blocked,
                reasonCode: 'execution_scope_mismatch',
                reasonMessage: 'O evento e a decisão não pertencem ao mesmo grupo empresarial e unidade.',
            );
        }

        if (
            ! $visitor instanceof VisitorRecord
            || ! $visit instanceof VisitRecord
        ) {
            return $this->outcome(
                status: AccessEventOperationalExecutionStatus::Blocked,
                reasonCode: 'execution_association_incomplete',
                reasonMessage: 'A decisão não possui visitante e visita válidos para execução.',
            );
        }

        if (
            $visitor->tenant_id
                !== $decision->tenant_id
            || $visitor->organization_id
                !== $decision->organization_id
            || $visit->tenant_id
                !== $decision->tenant_id
            || $visit->organization_id
                !== $decision->organization_id
            || $visit->visitor_id
                !== $visitor->id
        ) {
            return $this->outcome(
                status: AccessEventOperationalExecutionStatus::Blocked,
                reasonCode: 'execution_association_mismatch',
                reasonMessage: 'A associação da decisão não é compatível com o visitante e a visita.',
            );
        }

        if (
            $event->status
            !== AccessEventStatus::Processed
        ) {
            return $this->outcome(
                status: AccessEventOperationalExecutionStatus::Blocked,
                reasonCode: 'execution_event_not_processed',
                reasonMessage: 'O evento não está mais processado e a decisão precisa ser recalculada.',
            );
        }

        if (
            $event->visitor_id
                !== $decision->visitor_id
            || $event->visit_id
                !== $decision->visit_id
        ) {
            return $this->outcome(
                status: AccessEventOperationalExecutionStatus::Blocked,
                reasonCode: 'execution_event_association_changed',
                reasonMessage: 'A associação do evento foi alterada depois do cálculo da decisão.',
            );
        }

        $directionMatchesDecision =
            match ($decision->decision) {
                AccessEventOperationalDecision::CheckInCandidate => $event->direction
                        === AccessEventDirection::Entry,

                AccessEventOperationalDecision::CheckOutCandidate => $event->direction
                        === AccessEventDirection::Exit,

                default => false,
            };

        if (! $directionMatchesDecision) {
            return $this->outcome(
                status: AccessEventOperationalExecutionStatus::Blocked,
                reasonCode: 'execution_direction_mismatch',
                reasonMessage: 'A direção atual do evento não corresponde à decisão operacional.',
            );
        }

        if (
            $visitor->status
            !== VisitorStatus::Active
        ) {
            return $this->outcome(
                status: AccessEventOperationalExecutionStatus::Blocked,
                reasonCode: 'execution_visitor_inactive',
                reasonMessage: 'O visitante não está mais ativo para a operação.',
            );
        }

        if (
            $decision->decision
                === AccessEventOperationalDecision::CheckInCandidate
            && blank($visitor->photo_path)
        ) {
            return $this->outcome(
                status: AccessEventOperationalExecutionStatus::Blocked,
                reasonCode: 'execution_visitor_photo_missing',
                reasonMessage: 'O visitante não possui mais uma foto facial válida para a entrada.',
            );
        }

        $currentVisitStatus =
            $visit->status instanceof VisitStatus
                ? $visit->status
                : VisitStatus::tryFrom(
                    (string) $visit->status
                );

        if (! $currentVisitStatus instanceof VisitStatus) {
            return $this->outcome(
                status: AccessEventOperationalExecutionStatus::Blocked,
                reasonCode: 'execution_visit_status_invalid',
                reasonMessage: 'A visita possui um status operacional inválido.',
            );
        }

        $visitStatusMatchesDecision =
            match ($decision->decision) {
                AccessEventOperationalDecision::CheckInCandidate => $currentVisitStatus
                        === VisitStatus::Authorized,

                AccessEventOperationalDecision::CheckOutCandidate => $currentVisitStatus
                        === VisitStatus::InProgress,

                default => false,
            };

        if (! $visitStatusMatchesDecision) {
            return $this->outcome(
                status: AccessEventOperationalExecutionStatus::Blocked,
                reasonCode: 'execution_visit_status_changed',
                reasonMessage: 'O status da visita mudou depois do cálculo da decisão operacional.',
            );
        }

        return $this->outcome(
            status: AccessEventOperationalExecutionStatus::Pending,
            reasonCode: 'execution_ready_for_controlled_processing',
            reasonMessage: 'A tentativa está pronta para uma futura execução controlada, ainda não implementada.',
        );
    }

    /**
     * @return array{
     *     status: AccessEventOperationalExecutionStatus,
     *     reason_code: string,
     *     reason_message: string
     * }
     */
    private function outcome(
        AccessEventOperationalExecutionStatus $status,
        string $reasonCode,
        string $reasonMessage,
    ): array {
        return [
            'status' => $status,
            'reason_code' => $reasonCode,
            'reason_message' => $reasonMessage,
        ];
    }

    private function visitStatusValue(
        ?VisitRecord $visit
    ): ?string {
        if (! $visit instanceof VisitRecord) {
            return null;
        }

        if ($visit->status instanceof VisitStatus) {
            return $visit->status->value;
        }

        return filled($visit->status)
            ? (string) $visit->status
            : null;
    }

    /**
     * @param array{
     *     status: AccessEventOperationalExecutionStatus,
     *     reason_code: string,
     *     reason_message: string
     * } $outcome
     */
    private function isSameAttempt(
        AccessEventOperationalExecutionRecord $attempt,
        array $outcome,
        bool $automaticExecutionAllowed,
        ?string $visitStatus,
    ): bool {
        return $attempt->source
                === AccessEventOperationalExecutionSource::Automatic
            && $attempt->operator_user_id === null
            && $attempt->status === $outcome['status']
            && $attempt->reason_code
                === $outcome['reason_code']
            && $attempt->reason_message
                === $outcome['reason_message']
            && $attempt->automatic_execution_allowed
                === $automaticExecutionAllowed
            && $attempt->visit_status_before
                === $visitStatus
            && $attempt->visit_status_after
                === $visitStatus;
    }

    private function result(
        AccessEventOperationalExecutionRecord $record,
        bool $duplicate,
    ): RegisterAccessEventOperationalExecutionResult {
        return new RegisterAccessEventOperationalExecutionResult(
            executionId: $record->id,
            decisionId: $record->operational_decision_id,
            eventId: $record->access_event_id,
            attemptNumber: (int) $record->attempt_number,
            source: $record->source,
            status: $record->status,
            reasonCode: $record->reason_code,
            automaticExecutionAllowed: $record->automatic_execution_allowed,
            duplicate: $duplicate,
        );
    }
}
