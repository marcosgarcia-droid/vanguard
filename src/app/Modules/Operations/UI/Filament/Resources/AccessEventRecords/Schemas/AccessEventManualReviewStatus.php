<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Schemas;

use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewConsumptionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventManualReviewRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventOperationalDecisionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;

final class AccessEventManualReviewStatus
{
    /**
     * @return array{
     *     visible: bool,
     *     analysis_status: string,
     *     analysis_color: string,
     *     reviewed_by: string,
     *     reviewed_at: mixed,
     *     notes: string,
     *     release_status: string,
     *     release_color: string,
     *     release_consumed: bool,
     *     consumed_by: ?string,
     *     consumed_at: mixed,
     *     next_action: string
     * }
     */
    public static function summary(
        AccessEventRecord $record
    ): array {
        $summary = self::defaultSummary();

        $decision = self::latestDecision(
            $record
        );

        if (
            self::decisionState($decision)
            !== AccessEventOperationalDecision::ManualReview
        ) {
            return $summary;
        }

        $summary['visible'] = true;

        $review = self::latestReview(
            $record
        );

        if (
            ! $review
            instanceof AccessEventManualReviewRecord
        ) {
            return $summary;
        }

        $summary['reviewed_by'] =
            self::operatorName(
                $review->operator_name
            );

        $summary['reviewed_at'] =
            $review->reviewed_at;

        $summary['notes'] = trim(
            (string) $review->notes
        ) ?: 'Nenhuma observação registrada.';

        if (
            ! self::reviewMatchesDecision(
                review: $review,
                decision: $decision,
            )
        ) {
            $summary['analysis_status'] =
                'Análise desatualizada';

            $summary['analysis_color'] =
                'danger';

            $summary['next_action'] =
                'Registrar uma nova análise manual para a decisão operacional atual.';

            return $summary;
        }

        $disposition = self::disposition(
            $review
        );

        if (
            ! $disposition
            instanceof AccessEventManualReviewDisposition
        ) {
            $summary['analysis_status'] =
                'Situação não reconhecida';

            $summary['analysis_color'] =
                'danger';

            $summary['next_action'] =
                'Registrar uma nova análise manual para definir a situação operacional.';

            return $summary;
        }

        $summary['analysis_status'] =
            $disposition->label();

        $summary['analysis_color'] =
            self::analysisColor(
                $disposition
            );

        if (
            $disposition
            === AccessEventManualReviewDisposition::PendingCorrection
        ) {
            $summary['release_status'] =
                'Não concedida';

            $summary['release_color'] =
                'warning';

            $summary['next_action'] =
                'Corrigir a pendência e registrar uma nova análise manual.';

            return $summary;
        }

        if (
            $disposition
            === AccessEventManualReviewDisposition::ResolvedWithoutOperation
        ) {
            $summary['release_status'] =
                'Não aplicável';

            $summary['release_color'] =
                'gray';

            $summary['next_action'] =
                'Nenhuma ação operacional adicional; o evento foi encerrado sem registrar entrada ou saída.';

            return $summary;
        }

        $consumption = self::consumption(
            $review
        );

        if (
            $consumption
            instanceof AccessEventManualReviewConsumptionRecord
        ) {
            $summary['release_status'] =
                'Consumida';

            $summary['release_color'] =
                'gray';

            $summary['release_consumed'] =
                true;

            $summary['consumed_by'] =
                self::operatorName(
                    $consumption->operator_name
                );

            $summary['consumed_at'] =
                $consumption->consumed_at;

            $summary['next_action'] =
                'Registrar uma nova análise manual para liberar outra tentativa de reprocessamento.';

            return $summary;
        }

        $summary['release_status'] =
            'Disponível para uso único';

        $summary['release_color'] =
            'success';

        $summary['next_action'] =
            'Usar “Reprocessar fluxo” para recalcular a decisão operacional.';

        return $summary;
    }

    /**
     * @return array{
     *     visible: bool,
     *     analysis_status: string,
     *     analysis_color: string,
     *     reviewed_by: string,
     *     reviewed_at: mixed,
     *     notes: string,
     *     release_status: string,
     *     release_color: string,
     *     release_consumed: bool,
     *     consumed_by: ?string,
     *     consumed_at: mixed,
     *     next_action: string
     * }
     */
    private static function defaultSummary(): array
    {
        return [
            'visible' => false,

            'analysis_status' => 'Aguardando análise',

            'analysis_color' => 'warning',

            'reviewed_by' => 'Não registrado',

            'reviewed_at' => null,

            'notes' => 'Nenhuma análise manual registrada.',

            'release_status' => 'Não concedida',

            'release_color' => 'gray',

            'release_consumed' => false,
            'consumed_by' => null,
            'consumed_at' => null,

            'next_action' => 'Registrar uma análise manual para a decisão operacional atual.',
        ];
    }

    private static function latestDecision(
        AccessEventRecord $record
    ): ?AccessEventOperationalDecisionRecord {
        if (
            $record->relationLoaded(
                'latestOperationalDecision'
            )
        ) {
            $decision = $record->getRelation(
                'latestOperationalDecision'
            );

            return $decision
                instanceof AccessEventOperationalDecisionRecord
                    ? $decision
                    : null;
        }

        if (
            ! $record->exists
            || blank($record->getKey())
        ) {
            return null;
        }

        $decision = $record
            ->latestOperationalDecision()
            ->first();

        $record->setRelation(
            'latestOperationalDecision',
            $decision
        );

        return $decision;
    }

    private static function latestReview(
        AccessEventRecord $record
    ): ?AccessEventManualReviewRecord {
        if (
            $record->relationLoaded(
                'latestManualReview'
            )
        ) {
            $review = $record->getRelation(
                'latestManualReview'
            );

            return $review
                instanceof AccessEventManualReviewRecord
                    ? $review
                    : null;
        }

        if (
            ! $record->exists
            || blank($record->getKey())
        ) {
            return null;
        }

        $review = $record
            ->latestManualReview()
            ->first();

        $record->setRelation(
            'latestManualReview',
            $review
        );

        return $review;
    }

    private static function consumption(
        AccessEventManualReviewRecord $review
    ): ?AccessEventManualReviewConsumptionRecord {
        if (
            $review->relationLoaded(
                'reprocessConsumption'
            )
        ) {
            $consumption = $review->getRelation(
                'reprocessConsumption'
            );

            return $consumption
                instanceof AccessEventManualReviewConsumptionRecord
                    ? $consumption
                    : null;
        }

        if (
            ! $review->exists
            || blank($review->getKey())
        ) {
            return null;
        }

        $consumption = $review
            ->reprocessConsumption()
            ->first();

        $review->setRelation(
            'reprocessConsumption',
            $consumption
        );

        return $consumption;
    }

    private static function decisionState(
        ?AccessEventOperationalDecisionRecord $decision
    ): ?AccessEventOperationalDecision {
        if (
            ! $decision
            instanceof AccessEventOperationalDecisionRecord
        ) {
            return null;
        }

        $state = $decision->decision;

        return $state
            instanceof AccessEventOperationalDecision
                ? $state
                : AccessEventOperationalDecision::tryFrom(
                    (string) $state
                );
    }

    private static function disposition(
        AccessEventManualReviewRecord $review
    ): ?AccessEventManualReviewDisposition {
        $disposition = $review->disposition;

        return $disposition
            instanceof AccessEventManualReviewDisposition
                ? $disposition
                : AccessEventManualReviewDisposition::tryFrom(
                    (string) $disposition
                );
    }

    private static function reviewMatchesDecision(
        AccessEventManualReviewRecord $review,
        AccessEventOperationalDecisionRecord $decision,
    ): bool {
        return (string) $review
            ->operational_decision_id
                === (string) $decision->id
            && (int) $review->decision_version
                === (int) $decision->version;
    }

    private static function analysisColor(
        AccessEventManualReviewDisposition $disposition
    ): string {
        return match ($disposition) {
            AccessEventManualReviewDisposition::PendingCorrection => 'warning',

            AccessEventManualReviewDisposition::ReadyForReprocessing => 'success',

            AccessEventManualReviewDisposition::ResolvedWithoutOperation => 'gray',
        };
    }

    private static function operatorName(
        mixed $name
    ): string {
        $name = trim((string) $name);

        return $name !== ''
            ? $name
            : 'Não informado';
    }
}
