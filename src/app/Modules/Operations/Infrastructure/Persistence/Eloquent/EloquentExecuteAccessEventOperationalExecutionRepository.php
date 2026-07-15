<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Operations\Application\AccessControl\Events\Execute\ExecuteAccessEventOperationalExecutionRepository;
use App\Modules\Operations\Application\AccessControl\Events\Execute\ExecuteAccessEventOperationalExecutionResult;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionSource;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentExecuteAccessEventOperationalExecutionRepository implements ExecuteAccessEventOperationalExecutionRepository
{
    public function executeAutomaticAttempt(
        string $executionId,
        bool $automaticExecutionAllowed,
    ): ?ExecuteAccessEventOperationalExecutionResult {
        return DB::transaction(
            function () use (
                $executionId,
                $automaticExecutionAllowed
            ): ?ExecuteAccessEventOperationalExecutionResult {
                /*
                 * A primeira leitura serve apenas para descobrir a
                 * decisão. Os locks são adquiridos depois seguindo
                 * a mesma ordem usada no registro da tentativa.
                 */
                $snapshot =
                    AccessEventOperationalExecutionRecord::query()
                        ->find($executionId);

                if (
                    ! $snapshot
                    instanceof AccessEventOperationalExecutionRecord
                ) {
                    return null;
                }

                $decision =
                    AccessEventOperationalDecisionRecord::query()
                        ->lockForUpdate()
                        ->find(
                            $snapshot->operational_decision_id
                        );

                if (
                    ! $decision
                    instanceof AccessEventOperationalDecisionRecord
                ) {
                    throw new RuntimeException(
                        'A decisão relacionada à tentativa não foi encontrada.'
                    );
                }

                $event = AccessEventRecord::query()
                    ->lockForUpdate()
                    ->find(
                        $decision->access_event_id
                    );

                if (! $event instanceof AccessEventRecord) {
                    throw new RuntimeException(
                        'O evento relacionado à tentativa não foi encontrado.'
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

                $execution =
                    AccessEventOperationalExecutionRecord::query()
                        ->lockForUpdate()
                        ->find($executionId);

                if (
                    ! $execution
                    instanceof AccessEventOperationalExecutionRecord
                ) {
                    return null;
                }

                /*
                 * Uma tentativa concluída é idempotente e nunca
                 * executa novamente a operação da visita.
                 */
                if (
                    $execution->status
                    === AccessEventOperationalExecutionStatus::Executed
                ) {
                    return $this->result(
                        execution: $execution,
                        decision: $decision,
                        duplicate: true,
                    );
                }

                if ($execution->status->isFinal()) {
                    return $this->result(
                        execution: $execution,
                        decision: $decision,
                        duplicate: true,
                    );
                }

                $currentVisitStatus =
                    $this->visitStatus($visit);

                $latestExecution =
                    AccessEventOperationalExecutionRecord::query()
                        ->where(
                            'operational_decision_id',
                            $decision->id
                        )
                        ->orderByDesc('attempt_number')
                        ->lockForUpdate()
                        ->first();

                if (
                    ! $latestExecution
                    instanceof AccessEventOperationalExecutionRecord
                    || $latestExecution->id
                        !== $execution->id
                ) {
                    return $this->block(
                        execution: $execution,
                        decision: $decision,
                        reasonCode: 'stale_execution_attempt',
                        reasonMessage: 'A tentativa não é mais a versão operacional mais recente da decisão.',
                        visitStatus: $currentVisitStatus,
                    );
                }

                if (
                    $execution->operational_decision_id
                        !== $decision->id
                    || $execution->access_event_id
                        !== $event->id
                ) {
                    return $this->block(
                        execution: $execution,
                        decision: $decision,
                        reasonCode: 'execution_context_changed',
                        reasonMessage: 'O vínculo interno da tentativa foi alterado.',
                        visitStatus: $currentVisitStatus,
                    );
                }

                if (
                    $execution->status
                        !== AccessEventOperationalExecutionStatus::Pending
                    || $execution->reason_code
                        !== 'execution_ready_for_controlled_processing'
                ) {
                    return $this->block(
                        execution: $execution,
                        decision: $decision,
                        reasonCode: 'execution_attempt_not_pending',
                        reasonMessage: 'A tentativa não está mais pendente com o contexto original de execução.',
                        visitStatus: $currentVisitStatus,
                    );
                }

                $currentVisitStatusValue =
                    $currentVisitStatus?->value;

                if (
                    $execution->tenant_id
                        !== $decision->tenant_id
                    || $execution->organization_id
                        !== $decision->organization_id
                    || $execution->visitor_id
                        !== $decision->visitor_id
                    || $execution->visit_id
                        !== $decision->visit_id
                    || $execution->visit_status_before
                        !== $currentVisitStatusValue
                    || $execution->visit_status_after
                        !== $currentVisitStatusValue
                ) {
                    return $this->block(
                        execution: $execution,
                        decision: $decision,
                        reasonCode: 'execution_snapshot_mismatch',
                        reasonMessage: 'O snapshot da tentativa não corresponde mais ao contexto atual da visita.',
                        visitStatus: $currentVisitStatus,
                    );
                }
                if (
                    $execution->source
                    !== AccessEventOperationalExecutionSource::Automatic
                    || $execution->operator_user_id !== null
                ) {
                    return $this->block(
                        execution: $execution,
                        decision: $decision,
                        reasonCode: 'execution_source_not_automatic',
                        reasonMessage: 'A tentativa não possui uma origem automática válida.',
                        visitStatus: $currentVisitStatus,
                    );
                }

                if (! $automaticExecutionAllowed) {
                    return $this->block(
                        execution: $execution,
                        decision: $decision,
                        reasonCode: 'automatic_execution_disabled_at_execution',
                        reasonMessage: 'A execução automática está desativada no momento da operação.',
                        visitStatus: $currentVisitStatus,
                    );
                }

                if (! $execution->automatic_execution_allowed) {
                    return $this->block(
                        execution: $execution,
                        decision: $decision,
                        reasonCode: 'execution_attempt_not_authorized',
                        reasonMessage: 'A tentativa não foi registrada com autorização para execução automática.',
                        visitStatus: $currentVisitStatus,
                    );
                }

                if (
                    ! $latestDecision
                    instanceof AccessEventOperationalDecisionRecord
                    || $latestDecision->id !== $decision->id
                ) {
                    return $this->block(
                        execution: $execution,
                        decision: $decision,
                        reasonCode: 'stale_operational_decision',
                        reasonMessage: 'A decisão não é mais a versão operacional mais recente.',
                        visitStatus: $currentVisitStatus,
                    );
                }

                if (
                    ! $decision->decision->isCandidate()
                    || ! $decision->automatic_execution_enabled
                ) {
                    return $this->block(
                        execution: $execution,
                        decision: $decision,
                        reasonCode: 'decision_not_authorized_for_execution',
                        reasonMessage: 'A decisão não está autorizada para execução automática.',
                        visitStatus: $currentVisitStatus,
                    );
                }

                if (
                    $event->status
                        !== AccessEventStatus::Processed
                    || $event->tenant_id
                        !== $decision->tenant_id
                    || $event->organization_id
                        !== $decision->organization_id
                    || $event->visitor_id
                        !== $decision->visitor_id
                    || $event->visit_id
                        !== $decision->visit_id
                ) {
                    return $this->block(
                        execution: $execution,
                        decision: $decision,
                        reasonCode: 'execution_event_context_changed',
                        reasonMessage: 'O evento não mantém mais o contexto usado na decisão.',
                        visitStatus: $currentVisitStatus,
                    );
                }

                if (
                    ! $visitor instanceof VisitorRecord
                    || ! $visit instanceof VisitRecord
                    || $visitor->tenant_id
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
                    return $this->block(
                        execution: $execution,
                        decision: $decision,
                        reasonCode: 'execution_association_changed',
                        reasonMessage: 'O visitante e a visita não mantêm mais a associação da decisão.',
                        visitStatus: $currentVisitStatus,
                    );
                }

                if (
                    $visitor->status
                    !== VisitorStatus::Active
                ) {
                    return $this->block(
                        execution: $execution,
                        decision: $decision,
                        reasonCode: 'execution_visitor_inactive',
                        reasonMessage: 'O visitante não está ativo para a operação.',
                        visitStatus: $currentVisitStatus,
                    );
                }

                if (
                    $decision->decision
                        === AccessEventOperationalDecision::CheckInCandidate
                    && blank($visitor->photo_path)
                ) {
                    return $this->block(
                        execution: $execution,
                        decision: $decision,
                        reasonCode: 'execution_visitor_photo_missing',
                        reasonMessage: 'O visitante não possui foto facial válida para a entrada.',
                        visitStatus: $currentVisitStatus,
                    );
                }

                if (! $currentVisitStatus instanceof VisitStatus) {
                    return $this->block(
                        execution: $execution,
                        decision: $decision,
                        reasonCode: 'execution_visit_status_invalid',
                        reasonMessage: 'A visita possui um status operacional inválido.',
                        visitStatus: null,
                    );
                }

                $operationAt =
                    $event->occurred_at ?? now();

                return match ($decision->decision) {
                    AccessEventOperationalDecision::CheckInCandidate => $this->executeCheckIn(
                        execution: $execution,
                        decision: $decision,
                        event: $event,
                        visit: $visit,
                        currentStatus: $currentVisitStatus,
                        operationAt: $operationAt,
                    ),

                    AccessEventOperationalDecision::CheckOutCandidate => $this->executeCheckOut(
                        execution: $execution,
                        decision: $decision,
                        event: $event,
                        visit: $visit,
                        currentStatus: $currentVisitStatus,
                        operationAt: $operationAt,
                    ),

                    default => $this->block(
                        execution: $execution,
                        decision: $decision,
                        reasonCode: 'decision_not_executable',
                        reasonMessage: 'A decisão não representa uma operação executável.',
                        visitStatus: $currentVisitStatus,
                    ),
                };
            },
            3
        );
    }

    public function markFailed(
        string $executionId,
    ): void {
        DB::transaction(
            function () use ($executionId): void {
                $execution =
                    AccessEventOperationalExecutionRecord::query()
                        ->lockForUpdate()
                        ->find($executionId);

                if (
                    ! $execution
                    instanceof AccessEventOperationalExecutionRecord
                    || $execution->status
                        !== AccessEventOperationalExecutionStatus::Pending
                ) {
                    return;
                }

                $execution->fill([
                    'status' => AccessEventOperationalExecutionStatus::Failed,
                    'reason_code' => 'execution_unexpected_failure',
                    'reason_message' => 'Ocorreu uma falha inesperada durante a execução operacional.',
                    'completed_at' => now(),
                ]);

                $execution->save();
            }
        );
    }

    private function executeCheckIn(
        AccessEventOperationalExecutionRecord $execution,
        AccessEventOperationalDecisionRecord $decision,
        AccessEventRecord $event,
        VisitRecord $visit,
        VisitStatus $currentStatus,
        mixed $operationAt,
    ): ExecuteAccessEventOperationalExecutionResult {
        if ($currentStatus !== VisitStatus::Authorized) {
            return $this->block(
                execution: $execution,
                decision: $decision,
                reasonCode: 'execution_visit_status_changed',
                reasonMessage: 'A visita não está mais autorizada para registrar a entrada.',
                visitStatus: $currentStatus,
            );
        }

        if ($event->direction !== AccessEventDirection::Entry) {
            return $this->block(
                execution: $execution,
                decision: $decision,
                reasonCode: 'execution_direction_mismatch',
                reasonMessage: 'O evento não possui direção de entrada.',
                visitStatus: $currentStatus,
            );
        }

        $attributes = [
            'status' => VisitStatus::InProgress,
            'checked_in_by' => null,
            'checked_in_at' => $operationAt,
        ];

        $visit->fill($attributes);
        $visit->save();
        $visit->refresh();

        return $this->complete(
            execution: $execution,
            decision: $decision,
            reasonCode: 'automatic_check_in_executed',
            reasonMessage: 'A entrada da visita foi registrada pelo evento facial.',
            visitStatus: $visit->status,
        );
    }

    private function executeCheckOut(
        AccessEventOperationalExecutionRecord $execution,
        AccessEventOperationalDecisionRecord $decision,
        AccessEventRecord $event,
        VisitRecord $visit,
        VisitStatus $currentStatus,
        mixed $operationAt,
    ): ExecuteAccessEventOperationalExecutionResult {
        if ($currentStatus !== VisitStatus::InProgress) {
            return $this->block(
                execution: $execution,
                decision: $decision,
                reasonCode: 'execution_visit_status_changed',
                reasonMessage: 'A visita não está mais em andamento para registrar a saída.',
                visitStatus: $currentStatus,
            );
        }

        if ($event->direction !== AccessEventDirection::Exit) {
            return $this->block(
                execution: $execution,
                decision: $decision,
                reasonCode: 'execution_direction_mismatch',
                reasonMessage: 'O evento não possui direção de saída.',
                visitStatus: $currentStatus,
            );
        }

        if ($visit->checked_in_at === null) {
            return $this->block(
                execution: $execution,
                decision: $decision,
                reasonCode: 'execution_check_in_timestamp_missing',
                reasonMessage: 'A visita não possui horário de entrada para registrar a saída automática.',
                visitStatus: $currentStatus,
            );
        }

        if ($operationAt < $visit->checked_in_at) {
            return $this->block(
                execution: $execution,
                decision: $decision,
                reasonCode: 'execution_event_before_check_in',
                reasonMessage: 'O evento de saída ocorreu antes do horário registrado de entrada.',
                visitStatus: $currentStatus,
            );
        }

        $visit->fill([
            'status' => VisitStatus::Completed,
            'checked_out_by' => null,
            'checked_out_at' => $operationAt,
        ]);

        $visit->save();
        $visit->refresh();

        return $this->complete(
            execution: $execution,
            decision: $decision,
            reasonCode: 'automatic_check_out_executed',
            reasonMessage: 'A saída da visita foi registrada pelo evento facial.',
            visitStatus: $visit->status,
        );
    }

    private function block(
        AccessEventOperationalExecutionRecord $execution,
        AccessEventOperationalDecisionRecord $decision,
        string $reasonCode,
        string $reasonMessage,
        ?VisitStatus $visitStatus,
    ): ExecuteAccessEventOperationalExecutionResult {
        $execution->fill([
            'status' => AccessEventOperationalExecutionStatus::Blocked,
            'reason_code' => $reasonCode,
            'reason_message' => $reasonMessage,
            'visit_status_after' => $visitStatus?->value,
            'completed_at' => now(),
        ]);

        $execution->save();
        $execution->refresh();

        return $this->result(
            execution: $execution,
            decision: $decision,
            duplicate: false,
        );
    }

    private function complete(
        AccessEventOperationalExecutionRecord $execution,
        AccessEventOperationalDecisionRecord $decision,
        string $reasonCode,
        string $reasonMessage,
        VisitStatus $visitStatus,
    ): ExecuteAccessEventOperationalExecutionResult {
        $execution->fill([
            'status' => AccessEventOperationalExecutionStatus::Executed,
            'reason_code' => $reasonCode,
            'reason_message' => $reasonMessage,
            'visit_status_after' => $visitStatus->value,
            'completed_at' => now(),
        ]);

        $execution->save();
        $execution->refresh();

        return $this->result(
            execution: $execution,
            decision: $decision,
            duplicate: false,
        );
    }

    private function visitStatus(
        ?VisitRecord $visit
    ): ?VisitStatus {
        if (! $visit instanceof VisitRecord) {
            return null;
        }

        if ($visit->status instanceof VisitStatus) {
            return $visit->status;
        }

        return VisitStatus::tryFrom(
            (string) $visit->status
        );
    }

    private function result(
        AccessEventOperationalExecutionRecord $execution,
        AccessEventOperationalDecisionRecord $decision,
        bool $duplicate,
    ): ExecuteAccessEventOperationalExecutionResult {
        return new ExecuteAccessEventOperationalExecutionResult(
            executionId: $execution->id,
            decisionId: $execution->operational_decision_id,
            eventId: $execution->access_event_id,
            visitId: $execution->visit_id,
            decision: $decision->decision,
            status: $execution->status,
            reasonCode: $execution->reason_code,
            visitStatusBefore: $this->statusFromValue(
                $execution->visit_status_before
            ),
            visitStatusAfter: $this->statusFromValue(
                $execution->visit_status_after
            ),
            duplicate: $duplicate,
        );
    }

    private function statusFromValue(
        mixed $value
    ): ?VisitStatus {
        if ($value instanceof VisitStatus) {
            return $value;
        }

        if (blank($value)) {
            return null;
        }

        return VisitStatus::tryFrom(
            (string) $value
        );
    }
}
