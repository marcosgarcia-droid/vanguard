<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Operations\Application\AccessControl\Events\Process\ProcessAccessEventRepository;
use App\Modules\Operations\Application\AccessControl\Events\Process\ProcessAccessEventResult;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentProcessAccessEventRepository implements ProcessAccessEventRepository
{
    private const MAX_PROCESSING_ATTEMPTS = 65535;

    public function process(
        string $eventId
    ): ?ProcessAccessEventResult {
        return DB::transaction(
            function () use (
                $eventId
            ): ?ProcessAccessEventResult {
                $event = AccessEventRecord::query()
                    ->with('accessDevice')
                    ->lockForUpdate()
                    ->find($eventId);

                if (
                    ! $event
                    instanceof AccessEventRecord
                ) {
                    return null;
                }

                $status = $this->eventStatus(
                    $event
                );

                if ($status->isFinal()) {
                    return $this->result(
                        $event,
                        duplicate: true
                    );
                }

                $processingAttempts =
                    $this->nextProcessingAttempt(
                        $event
                    );

                $externalPersonId = trim(
                    (string) $event->external_person_id
                );

                if ($externalPersonId === '') {
                    return $this->keepPending(
                        event: $event,
                        visitor: null,
                        resultCode: 'person_reference_missing',
                        resultMessage: 'O evento não possui uma referência externa de pessoa.',
                        processingAttempts: $processingAttempts,
                    );
                }

                $device = $event->accessDevice;

                if (
                    ! $device
                    instanceof AccessDeviceRecord
                ) {
                    throw new RuntimeException(
                        'O dispositivo associado ao evento não está disponível.'
                    );
                }

                $provider = strtolower(
                    trim(
                        (string) $device->provider
                    )
                );

                if ($provider === '') {
                    throw new RuntimeException(
                        'O provider do dispositivo não está definido.'
                    );
                }

                $visitor = VisitorRecord::query()
                    ->where(
                        'tenant_id',
                        $event->tenant_id
                    )
                    ->where(
                        'organization_id',
                        $event->organization_id
                    )
                    ->where(
                        'external_source',
                        $provider
                    )
                    ->where(
                        'external_id',
                        $externalPersonId
                    )
                    ->first();

                if (
                    ! $visitor
                    instanceof VisitorRecord
                ) {
                    return $this->keepPending(
                        event: $event,
                        visitor: null,
                        resultCode: 'visitor_not_found',
                        resultMessage: 'Nenhum visitante ativo foi localizado para a referência externa recebida.',
                        processingAttempts: $processingAttempts,
                    );
                }

                if (
                    $visitor->status
                    !== VisitorStatus::Active
                ) {
                    return $this->keepPending(
                        event: $event,
                        visitor: null,
                        resultCode: 'visitor_inactive',
                        resultMessage: 'O visitante associado à referência externa está inativo.',
                        processingAttempts: $processingAttempts,
                    );
                }

                $eligibleVisitStatus =
                    $this->eligibleVisitStatus(
                        $event->direction
                    );

                $eligibleVisits = VisitRecord::query()
                    ->where(
                        'tenant_id',
                        $event->tenant_id
                    )
                    ->where(
                        'organization_id',
                        $event->organization_id
                    )
                    ->where(
                        'visitor_id',
                        $visitor->id
                    )
                    ->where(
                        'status',
                        $eligibleVisitStatus->value
                    )
                    ->orderBy('expected_start_at')
                    ->orderBy('id')
                    ->limit(2)
                    ->get();

                if ($eligibleVisits->isEmpty()) {
                    return $this->keepPending(
                        event: $event,
                        visitor: $visitor,
                        resultCode: 'visitor_associated_no_visit',
                        resultMessage: 'O visitante foi associado, mas nenhuma visita está em condição operacional compatível.',
                        processingAttempts: $processingAttempts,
                    );
                }

                if ($eligibleVisits->count() > 1) {
                    return $this->keepPending(
                        event: $event,
                        visitor: $visitor,
                        resultCode: 'multiple_eligible_visits',
                        resultMessage: 'Mais de uma visita está elegível; nenhuma escolha automática foi realizada.',
                        processingAttempts: $processingAttempts,
                    );
                }

                $visit = $eligibleVisits->first();

                if (! $visit instanceof VisitRecord) {
                    throw new RuntimeException(
                        'A visita elegível não pôde ser carregada.'
                    );
                }

                $event
                    ->forceFill([
                        'visitor_id' => $visitor->id,
                        'visit_id' => $visit->id,
                        'status' => AccessEventStatus::Processed,
                        'result_code' => 'association_completed',
                        'result_message' => 'Evento associado ao visitante e à visita sem alterar o estado operacional da visita.',
                        'processed_at' => now(),
                        'processing_attempts' => $processingAttempts,
                    ])
                    ->saveQuietly();

                $event->refresh();

                return $this->result(
                    $event,
                    duplicate: false
                );
            }
        );
    }

    public function markFailed(
        string $eventId,
        string $message
    ): void {
        DB::transaction(
            function () use (
                $eventId,
                $message
            ): void {
                $event = AccessEventRecord::query()
                    ->lockForUpdate()
                    ->find($eventId);

                if (
                    ! $event
                    instanceof AccessEventRecord
                ) {
                    return;
                }

                $status = $this->eventStatus(
                    $event
                );

                if ($status->isFinal()) {
                    return;
                }

                $message = trim($message);

                if ($message === '') {
                    $message = 'Falha inesperada durante o processamento controlado do evento.';
                }

                $event
                    ->forceFill([
                        'status' => AccessEventStatus::Failed,
                        'result_code' => 'processing_failed',
                        'result_message' => mb_substr(
                            $message,
                            0,
                            1000
                        ),
                        'processed_at' => null,
                        'processing_attempts' => $this
                            ->nextProcessingAttempt(
                                $event
                            ),
                    ])
                    ->saveQuietly();
            }
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
            throw new RuntimeException(
                'O evento possui um status inválido.'
            );
        }

        return $status;
    }

    private function nextProcessingAttempt(
        AccessEventRecord $event
    ): int {
        return min(
            self::MAX_PROCESSING_ATTEMPTS,
            ((int) $event->processing_attempts) + 1
        );
    }

    private function keepPending(
        AccessEventRecord $event,
        ?VisitorRecord $visitor,
        string $resultCode,
        string $resultMessage,
        int $processingAttempts,
    ): ProcessAccessEventResult {
        $event
            ->forceFill([
                'visitor_id' => $visitor?->id,
                'visit_id' => null,
                'status' => AccessEventStatus::PendingAssociation,
                'result_code' => $resultCode,
                'result_message' => $resultMessage,
                'processed_at' => null,
                'processing_attempts' => $processingAttempts,
            ])
            ->saveQuietly();

        $event->refresh();

        return $this->result(
            $event,
            duplicate: false
        );
    }

    private function result(
        AccessEventRecord $event,
        bool $duplicate
    ): ProcessAccessEventResult {
        return new ProcessAccessEventResult(
            eventId: $event->id,
            status: $this->eventStatus(
                $event
            ),
            visitorId: $event->visitor_id,
            visitId: $event->visit_id,
            resultCode: (string) (
                $event->result_code
                ?: 'not_informed'
            ),
            processingAttempts: (int) $event->processing_attempts,
            duplicate: $duplicate,
        );
    }
}
