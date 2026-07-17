<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowContext;
use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowException;
use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowRepository;
use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class EloquentReprocessAccessEventFlowRepository implements ReprocessAccessEventFlowRepository
{
    public function prepare(
        string $eventId,
        int $operatorUserId,
        string $idempotencyKey,
    ): ?ReprocessAccessEventFlowContext {
        try {
            return DB::transaction(
                fn (): ?ReprocessAccessEventFlowContext => $this->prepareWithinTransaction(
                    eventId: $eventId,
                    operatorUserId: $operatorUserId,
                    idempotencyKey: $idempotencyKey,
                ),
                3
            );
        } catch (
            ReprocessAccessEventFlowException $exception
        ) {
            throw $exception;
        } catch (QueryException $exception) {
            $existing = $this
                ->latestConsumptionForCurrentReview(
                    $eventId
                );

            if (
                $existing
                instanceof AccessEventManualReviewConsumptionRecord
            ) {
                throw $this->consumedException(
                    $existing,
                    $exception
                );
            }

            throw $exception;
        }
    }

    private function prepareWithinTransaction(
        string $eventId,
        int $operatorUserId,
        string $idempotencyKey,
    ): ?ReprocessAccessEventFlowContext {
        $existingByKey =
            AccessEventManualReviewConsumptionRecord::query()
                ->where(
                    'idempotency_key',
                    $idempotencyKey
                )
                ->lockForUpdate()
                ->first();

        if (
            $existingByKey
            instanceof AccessEventManualReviewConsumptionRecord
        ) {
            throw $this->consumedException(
                $existingByKey
            );
        }

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
            throw new ReprocessAccessEventFlowException(
                'O operador responsável não foi encontrado.'
            );
        }

        try {
            Gate::forUser($operator)->authorize(
                'reprocessFlow',
                $event
            );
        } catch (
            AuthorizationException $exception
        ) {
            throw new ReprocessAccessEventFlowException(
                message: 'O operador não possui autorização para reprocessar este evento.',
                previous: $exception,
            );
        }

        $decision =
            AccessEventOperationalDecisionRecord::query()
                ->where(
                    'access_event_id',
                    $event->id
                )
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();

        if (
            ! $decision
            instanceof AccessEventOperationalDecisionRecord
        ) {
            return $this->regularContext(
                event: $event,
                decision: null,
            );
        }

        $decisionState =
            $this->decisionState($decision);

        if (
            $decisionState
            !== AccessEventOperationalDecision::ManualReview
        ) {
            return $this->regularContext(
                event: $event,
                decision: $decision,
            );
        }

        $this->ensureDecisionMatchesEvent(
            event: $event,
            decision: $decision,
        );

        $review =
            AccessEventManualReviewRecord::query()
                ->where(
                    'access_event_id',
                    $event->id
                )
                ->orderByDesc('reviewed_at')
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

        if (
            ! $review
            instanceof AccessEventManualReviewRecord
        ) {
            throw new ReprocessAccessEventFlowException(
                'Registre uma análise manual antes de reprocessar este evento.'
            );
        }

        $this->ensureReviewMatchesCurrentDecision(
            event: $event,
            decision: $decision,
            review: $review,
        );

        $disposition =
            $this->reviewDisposition($review);

        if (
            $disposition
            === AccessEventManualReviewDisposition::PendingCorrection
        ) {
            throw new ReprocessAccessEventFlowException(
                'A análise manual permanece aguardando correção.'
            );
        }

        if (
            $disposition
            === AccessEventManualReviewDisposition::ResolvedWithoutOperation
        ) {
            throw new ReprocessAccessEventFlowException(
                'A revisão manual foi encerrada sem operação e não pode ser reprocessada.'
            );
        }

        if (
            ! $disposition
            instanceof AccessEventManualReviewDisposition
            || ! $disposition->requestsReprocessing()
        ) {
            throw new ReprocessAccessEventFlowException(
                'A situação atual da análise manual não permite o reprocessamento.'
            );
        }

        $existingConsumption =
            AccessEventManualReviewConsumptionRecord::query()
                ->where(
                    'manual_review_id',
                    $review->id
                )
                ->lockForUpdate()
                ->first();

        if (
            $existingConsumption
            instanceof AccessEventManualReviewConsumptionRecord
        ) {
            throw $this->consumedException(
                $existingConsumption
            );
        }

        /*
         * A criação ocorre antes da orquestração. A unique
         * constraint por manual_review_id impede duas
         * utilizações simultâneas da mesma liberação.
         */
        $consumption =
            AccessEventManualReviewConsumptionRecord::query()
                ->create([
                    'access_event_id' => $event->id,
                    'manual_review_id' => $review->id,

                    'operational_decision_id' => $decision->id,

                    'tenant_id' => $event->tenant_id,

                    'organization_id' => $event->organization_id,

                    'visitor_id' => $event->visitor_id,
                    'visit_id' => $event->visit_id,

                    'operator_user_id' => $operator->id,

                    'idempotency_key' => $idempotencyKey,

                    'operator_name' => $this->operatorName(
                        $operator
                    ),

                    'decision_version' => $decision->version,

                    'disposition' => $disposition,
                    'consumed_at' => now(),
                ]);

        return new ReprocessAccessEventFlowContext(
            eventId: $event->id,
            manualReviewReleaseUsed: true,
            decisionId: $decision->id,
            manualReviewId: $review->id,

            manualReviewConsumptionId: $consumption->id,
        );
    }

    private function regularContext(
        AccessEventRecord $event,
        ?AccessEventOperationalDecisionRecord $decision,
    ): ReprocessAccessEventFlowContext {
        return new ReprocessAccessEventFlowContext(
            eventId: $event->id,
            manualReviewReleaseUsed: false,
            decisionId: $decision?->id,
            manualReviewId: null,
            manualReviewConsumptionId: null,
        );
    }

    private function decisionState(
        AccessEventOperationalDecisionRecord $decision
    ): ?AccessEventOperationalDecision {
        $state = $decision->decision;

        return $state
            instanceof AccessEventOperationalDecision
                ? $state
                : AccessEventOperationalDecision::tryFrom(
                    (string) $state
                );
    }

    private function reviewDisposition(
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

    private function ensureDecisionMatchesEvent(
        AccessEventRecord $event,
        AccessEventOperationalDecisionRecord $decision,
    ): void {
        if (
            (string) $decision->access_event_id
                !== (string) $event->id
            || (string) $decision->tenant_id
                !== (string) $event->tenant_id
            || (string) $decision->organization_id
                !== (string) $event->organization_id
            || (string) $decision->visitor_id
                !== (string) $event->visitor_id
            || (string) $decision->visit_id
                !== (string) $event->visit_id
        ) {
            throw new ReprocessAccessEventFlowException(
                'O contexto da decisão operacional não corresponde mais ao evento.'
            );
        }
    }

    private function ensureReviewMatchesCurrentDecision(
        AccessEventRecord $event,
        AccessEventOperationalDecisionRecord $decision,
        AccessEventManualReviewRecord $review,
    ): void {
        if (
            (string) $review->access_event_id
                !== (string) $event->id
            || (string) $review->operational_decision_id
                !== (string) $decision->id
            || (int) $review->decision_version
                !== (int) $decision->version
            || (string) $review->tenant_id
                !== (string) $event->tenant_id
            || (string) $review->organization_id
                !== (string) $event->organization_id
            || (string) $review->visitor_id
                !== (string) $event->visitor_id
            || (string) $review->visit_id
                !== (string) $event->visit_id
        ) {
            throw new ReprocessAccessEventFlowException(
                'A análise manual mais recente não corresponde à decisão operacional atual.'
            );
        }
    }

    private function consumedException(
        AccessEventManualReviewConsumptionRecord $consumption,
        ?Throwable $previous = null,
    ): ReprocessAccessEventFlowException {
        return new ReprocessAccessEventFlowException(
            message: 'A liberação desta análise manual já foi utilizada. Registre uma nova análise para reprocessar novamente.',
            previous: $previous,
            manualReviewReleaseConsumed: true,
            manualReviewId: $consumption->manual_review_id,

            manualReviewConsumptionId: $consumption->id,
        );
    }

    private function latestConsumptionForCurrentReview(
        string $eventId
    ): ?AccessEventManualReviewConsumptionRecord {
        $review =
            AccessEventManualReviewRecord::query()
                ->where(
                    'access_event_id',
                    $eventId
                )
                ->orderByDesc('reviewed_at')
                ->orderByDesc('created_at')
                ->first();

        if (
            ! $review
            instanceof AccessEventManualReviewRecord
        ) {
            return null;
        }

        return AccessEventManualReviewConsumptionRecord::query()
            ->where(
                'manual_review_id',
                $review->id
            )
            ->first();
    }

    private function operatorName(
        User $operator
    ): string {
        $name = trim((string) $operator->name);

        if ($name !== '') {
            return $name;
        }

        $email = trim((string) $operator->email);

        return $email !== ''
            ? $email
            : 'Usuário #'.$operator->id;
    }
}
