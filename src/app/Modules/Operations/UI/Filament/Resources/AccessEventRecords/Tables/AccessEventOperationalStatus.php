<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Tables;

use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewConsumptionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalExecutionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;

final class AccessEventOperationalStatus
{
    /**
     * @return array{
     *     code: string,
     *     label: string,
     *     color: string,
     *     description: string
     * }
     */
    public static function summary(
        AccessEventRecord $record
    ): array {
        $eventStatus = self::eventStatus(
            $record->status
        );

        $initialState = match ($eventStatus) {
            AccessEventStatus::Failed => self::result(
                code: 'processing_failed',
                label: 'Falha no processamento',
                color: 'danger',
                description: 'Verifique o motivo do processamento antes de uma nova tentativa.',
            ),

            AccessEventStatus::PendingAssociation => self::result(
                code: 'pending_association',
                label: 'Aguardando associação',
                color: 'warning',
                description: 'Associe o visitante e, quando aplicável, a visita.',
            ),

            AccessEventStatus::Received => self::result(
                code: 'awaiting_processing',
                label: 'Aguardando processamento',
                color: 'info',
                description: 'O evento foi recebido e ainda não concluiu o processamento.',
            ),

            AccessEventStatus::Ignored => self::result(
                code: 'ignored',
                label: 'Evento ignorado',
                color: 'gray',
                description: 'O evento não exige tratamento operacional.',
            ),

            AccessEventStatus::Processed => null,

            default => self::result(
                code: 'unknown_processing_status',
                label: 'Situação não reconhecida',
                color: 'danger',
                description: 'O estado do processamento não pôde ser interpretado.',
            ),
        };

        if (is_array($initialState)) {
            return $initialState;
        }

        $decision = self::latestDecision(
            $record
        );

        if (
            ! $decision
            instanceof AccessEventOperationalDecisionRecord
        ) {
            return self::result(
                code: 'awaiting_decision',
                label: 'Aguardando decisão operacional',
                color: 'info',
                description: 'O evento processado ainda não possui decisão operacional.',
            );
        }

        $decisionState = self::decisionState(
            $decision->decision
        );

        if (
            $decisionState
            === AccessEventOperationalDecision::ManualReview
        ) {
            return self::manualReviewSummary(
                record: $record,
                decision: $decision,
            );
        }

        $execution = self::latestExecution(
            $record
        );

        $executionState = self::executionState(
            $execution?->status
        );

        $executionSummary = match ($executionState) {
            AccessEventOperationalExecutionStatus::Failed => self::result(
                code: 'execution_failed',
                label: 'Falha na tentativa',
                color: 'danger',
                description: 'Consulte o motivo da última tentativa antes de reprocessar.',
            ),

            AccessEventOperationalExecutionStatus::Blocked => self::result(
                code: 'execution_blocked',
                label: 'Tentativa bloqueada',
                color: 'warning',
                description: 'Consulte o bloqueio registrado antes de uma nova tentativa.',
            ),

            AccessEventOperationalExecutionStatus::Pending => self::result(
                code: 'execution_pending',
                label: 'Tentativa pendente',
                color: 'info',
                description: 'A tentativa operacional ainda não foi concluída.',
            ),

            AccessEventOperationalExecutionStatus::Executed => self::result(
                code: 'execution_completed',
                label: 'Operação executada',
                color: 'success',
                description: 'A operação associada à decisão foi concluída.',
            ),

            /*
             * Uma tentativa ignorada normalmente acompanha decisões que
             * não exigem execução. Nesse caso, a decisão é mais útil para
             * a triagem do que o status técnico "skipped".
             */
            AccessEventOperationalExecutionStatus::Skipped,
            null => null,
        };

        if (is_array($executionSummary)) {
            return $executionSummary;
        }

        return match ($decisionState) {
            AccessEventOperationalDecision::CheckInCandidate => self::result(
                code: 'check_in_candidate',
                label: 'Candidato a entrada',
                color: 'success',
                description: 'O evento possui uma decisão operacional candidata a entrada.',
            ),

            AccessEventOperationalDecision::CheckOutCandidate => self::result(
                code: 'check_out_candidate',
                label: 'Candidato a saída',
                color: 'warning',
                description: 'O evento possui uma decisão operacional candidata a saída.',
            ),

            AccessEventOperationalDecision::NoAction => self::result(
                code: 'no_action',
                label: 'Sem ação operacional',
                color: 'gray',
                description: 'A decisão atual não exige entrada ou saída.',
            ),

            default => self::result(
                code: 'unknown_decision',
                label: 'Decisão não reconhecida',
                color: 'danger',
                description: 'A última decisão operacional não pôde ser interpretada.',
            ),
        };
    }

    /**
     * @return array{
     *     code: string,
     *     label: string,
     *     color: string,
     *     description: string
     * }
     */
    private static function manualReviewSummary(
        AccessEventRecord $record,
        AccessEventOperationalDecisionRecord $decision,
    ): array {
        $review = self::latestReview(
            $record
        );

        if (
            ! $review
            instanceof AccessEventManualReviewRecord
        ) {
            return self::result(
                code: 'awaiting_manual_review',
                label: 'Aguardando análise manual',
                color: 'warning',
                description: 'Registre a análise manual para definir o tratamento do evento.',
            );
        }

        if (
            (string) $review->operational_decision_id
                !== (string) $decision->id
            || (int) $review->decision_version
                !== (int) $decision->version
        ) {
            return self::result(
                code: 'outdated_manual_review',
                label: 'Análise manual desatualizada',
                color: 'danger',
                description: 'Registre uma nova análise para a decisão operacional atual.',
            );
        }

        $disposition = self::reviewDisposition(
            $review->disposition
        );

        return match ($disposition) {
            AccessEventManualReviewDisposition::PendingCorrection => self::result(
                code: 'pending_correction',
                label: 'Aguardando correção',
                color: 'warning',
                description: 'Corrija a pendência e registre uma nova análise manual.',
            ),

            AccessEventManualReviewDisposition::ReadyForReprocessing => self::readyReviewSummary(
                $review
            ),

            AccessEventManualReviewDisposition::ResolvedWithoutOperation => self::result(
                code: 'resolved_without_operation',
                label: 'Revisão encerrada sem operação',
                color: 'gray',
                description: 'A análise foi encerrada sem registrar entrada ou saída.',
            ),

            default => self::result(
                code: 'unknown_manual_review',
                label: 'Revisão manual não reconhecida',
                color: 'danger',
                description: 'Registre uma nova análise manual para definir o tratamento.',
            ),
        };
    }

    /**
     * @return array{
     *     code: string,
     *     label: string,
     *     color: string,
     *     description: string
     * }
     */
    private static function readyReviewSummary(
        AccessEventManualReviewRecord $review
    ): array {
        if (
            self::consumption($review)
            instanceof AccessEventManualReviewConsumptionRecord
        ) {
            return self::result(
                code: 'manual_review_release_consumed',
                label: 'Liberação consumida',
                color: 'warning',
                description: 'Registre uma nova análise manual para liberar outra tentativa.',
            );
        }

        return self::result(
            code: 'ready_for_reprocessing',
            label: 'Pronto para reprocessamento',
            color: 'success',
            description: 'Use “Reprocessar fluxo” para recalcular a decisão operacional.',
        );
    }

    private static function latestDecision(
        AccessEventRecord $record
    ): ?AccessEventOperationalDecisionRecord {
        if (
            ! $record->relationLoaded(
                'latestOperationalDecision'
            )
        ) {
            return null;
        }

        $decision = $record->getRelation(
            'latestOperationalDecision'
        );

        return $decision
            instanceof AccessEventOperationalDecisionRecord
                ? $decision
                : null;
    }

    private static function latestExecution(
        AccessEventRecord $record
    ): ?AccessEventOperationalExecutionRecord {
        if (
            ! $record->relationLoaded(
                'latestOperationalExecution'
            )
        ) {
            return null;
        }

        $execution = $record->getRelation(
            'latestOperationalExecution'
        );

        return $execution
            instanceof AccessEventOperationalExecutionRecord
                ? $execution
                : null;
    }

    private static function latestReview(
        AccessEventRecord $record
    ): ?AccessEventManualReviewRecord {
        if (
            ! $record->relationLoaded(
                'latestManualReview'
            )
        ) {
            return null;
        }

        $review = $record->getRelation(
            'latestManualReview'
        );

        return $review
            instanceof AccessEventManualReviewRecord
                ? $review
                : null;
    }

    private static function consumption(
        AccessEventManualReviewRecord $review
    ): ?AccessEventManualReviewConsumptionRecord {
        if (
            ! $review->relationLoaded(
                'reprocessConsumption'
            )
        ) {
            return null;
        }

        $consumption = $review->getRelation(
            'reprocessConsumption'
        );

        return $consumption
            instanceof AccessEventManualReviewConsumptionRecord
                ? $consumption
                : null;
    }

    private static function eventStatus(
        mixed $state
    ): ?AccessEventStatus {
        return $state instanceof AccessEventStatus
            ? $state
            : AccessEventStatus::tryFrom(
                (string) $state
            );
    }

    private static function decisionState(
        mixed $state
    ): ?AccessEventOperationalDecision {
        return $state
            instanceof AccessEventOperationalDecision
                ? $state
                : AccessEventOperationalDecision::tryFrom(
                    (string) $state
                );
    }

    private static function executionState(
        mixed $state
    ): ?AccessEventOperationalExecutionStatus {
        if ($state === null || $state === '') {
            return null;
        }

        return $state
            instanceof AccessEventOperationalExecutionStatus
                ? $state
                : AccessEventOperationalExecutionStatus::tryFrom(
                    (string) $state
                );
    }

    private static function reviewDisposition(
        mixed $state
    ): ?AccessEventManualReviewDisposition {
        return $state
            instanceof AccessEventManualReviewDisposition
                ? $state
                : AccessEventManualReviewDisposition::tryFrom(
                    (string) $state
                );
    }

    /**
     * @return array{
     *     code: string,
     *     label: string,
     *     color: string,
     *     description: string
     * }
     */
    private static function result(
        string $code,
        string $label,
        string $color,
        string $description,
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'color' => $color,
            'description' => $description,
        ];
    }
}
