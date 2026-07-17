<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Operations\Application\AccessControl\Events\ManualReview\RecordAccessEventManualReviewException;
use App\Modules\Operations\Application\AccessControl\Events\ManualReview\RecordAccessEventManualReviewRepository;
use App\Modules\Operations\Application\AccessControl\Events\ManualReview\RecordAccessEventManualReviewResult;
use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use Illuminate\Support\Facades\DB;

final class EloquentRecordAccessEventManualReviewRepository implements RecordAccessEventManualReviewRepository
{
    public function record(
        string $eventId,
        int $operatorUserId,
        AccessEventManualReviewDisposition $disposition,
        string $notes,
        string $idempotencyKey,
    ): ?RecordAccessEventManualReviewResult {
        return DB::transaction(
            function () use (
                $eventId,
                $operatorUserId,
                $disposition,
                $notes,
                $idempotencyKey,
            ): ?RecordAccessEventManualReviewResult {
                $existing =
                    AccessEventManualReviewRecord::query()
                        ->where(
                            'idempotency_key',
                            $idempotencyKey
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $existing
                    instanceof AccessEventManualReviewRecord
                ) {
                    $this->ensureIdempotentRequestMatches(
                        record: $existing,
                        eventId: $eventId,
                        operatorUserId: $operatorUserId,
                        disposition: $disposition,
                        notes: $notes,
                    );

                    return $this->result(
                        record: $existing,
                        duplicate: true,
                    );
                }

                $event = AccessEventRecord::query()
                    ->lockForUpdate()
                    ->find($eventId);

                if (! $event instanceof AccessEventRecord) {
                    return null;
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
                    || $decision->decision
                        !== AccessEventOperationalDecision::ManualReview
                ) {
                    throw new RecordAccessEventManualReviewException(
                        'Somente eventos com decisão atual de revisão manual podem receber uma análise.'
                    );
                }

                $this->ensureDecisionMatchesEvent(
                    event: $event,
                    decision: $decision,
                );

                $operator = User::query()
                    ->lockForUpdate()
                    ->find($operatorUserId);

                if (! $operator instanceof User) {
                    throw new RecordAccessEventManualReviewException(
                        'O operador responsável não foi encontrado.'
                    );
                }

                if (
                    ! $operator->can(
                        'resolveManualReview',
                        $event
                    )
                ) {
                    throw new RecordAccessEventManualReviewException(
                        'O operador não possui autorização para analisar este evento.'
                    );
                }

                $reviewedAt = now();

                $record =
                    AccessEventManualReviewRecord::query()
                        ->create([
                            'access_event_id' => $event->id,
                            'operational_decision_id' => $decision->id,
                            'tenant_id' => $event->tenant_id,
                            'organization_id' => $event->organization_id,
                            'visitor_id' => $event->visitor_id,
                            'visit_id' => $event->visit_id,
                            'idempotency_key' => $idempotencyKey,
                            'operator_user_id' => $operator->id,
                            'operator_name' => $this->operatorName(
                                $operator
                            ),
                            'decision_version' => $decision->version,
                            'decision_reason_code' => $decision->reason_code,
                            'decision_reason_message' => $decision->reason_message,
                            'disposition' => $disposition,
                            'notes' => $notes,
                            'reviewed_at' => $reviewedAt,
                        ]);

                return $this->result(
                    record: $record,
                    duplicate: false,
                );
            },
            3
        );
    }

    private function ensureDecisionMatchesEvent(
        AccessEventRecord $event,
        AccessEventOperationalDecisionRecord $decision,
    ): void {
        if (
            (string) $decision->tenant_id
                !== (string) $event->tenant_id
            || (string) $decision->organization_id
                !== (string) $event->organization_id
            || (string) $decision->visitor_id
                !== (string) $event->visitor_id
            || (string) $decision->visit_id
                !== (string) $event->visit_id
        ) {
            throw new RecordAccessEventManualReviewException(
                'O contexto da decisão de revisão não corresponde mais ao evento.'
            );
        }
    }

    private function ensureIdempotentRequestMatches(
        AccessEventManualReviewRecord $record,
        string $eventId,
        int $operatorUserId,
        AccessEventManualReviewDisposition $disposition,
        string $notes,
    ): void {
        if (
            (string) $record->access_event_id
                !== $eventId
            || (int) $record->operator_user_id
                !== $operatorUserId
            || $record->disposition
                !== $disposition
            || trim((string) $record->notes)
                !== $notes
        ) {
            throw new RecordAccessEventManualReviewException(
                'A chave de idempotência já foi utilizada em outra análise manual.'
            );
        }
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

    private function result(
        AccessEventManualReviewRecord $record,
        bool $duplicate,
    ): RecordAccessEventManualReviewResult {
        return new RecordAccessEventManualReviewResult(
            reviewId: $record->id,
            eventId: $record->access_event_id,
            decisionId: $record->operational_decision_id,
            disposition: $record->disposition,
            reviewedAt: $record->reviewed_at
                ->toImmutable(),
            duplicate: $duplicate,
        );
    }
}
