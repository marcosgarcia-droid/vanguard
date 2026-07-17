<?php

namespace App\Modules\Operations\Application\AccessControl\Events\ManualReview;

use App\Support\Contracts\UseCase;
use Illuminate\Support\Str;
use Throwable;

final readonly class RecordAccessEventManualReviewUseCase implements UseCase
{
    private const MIN_NOTES_LENGTH = 10;

    private const MAX_NOTES_LENGTH = 2000;

    public function __construct(
        private RecordAccessEventManualReviewRepository $repository,
    ) {}

    public function execute(
        RecordAccessEventManualReviewCommand $command
    ): RecordAccessEventManualReviewResult {
        $eventId = trim($command->eventId);

        if (! Str::isUuid($eventId)) {
            throw new RecordAccessEventManualReviewException(
                'O identificador do evento é inválido.'
            );
        }

        if ($command->operatorUserId <= 0) {
            throw new RecordAccessEventManualReviewException(
                'O operador responsável é obrigatório.'
            );
        }

        $idempotencyKey = trim(
            $command->idempotencyKey
        );

        if (! Str::isUuid($idempotencyKey)) {
            throw new RecordAccessEventManualReviewException(
                'A chave de idempotência da revisão é inválida.'
            );
        }

        $notes = trim($command->notes);
        $notesLength = mb_strlen($notes);

        if ($notesLength < self::MIN_NOTES_LENGTH) {
            throw new RecordAccessEventManualReviewException(
                'Informe uma observação com pelo menos 10 caracteres.'
            );
        }

        if ($notesLength > self::MAX_NOTES_LENGTH) {
            throw new RecordAccessEventManualReviewException(
                'A observação da revisão excede 2.000 caracteres.'
            );
        }

        try {
            $result = $this->repository->record(
                eventId: $eventId,
                operatorUserId: $command->operatorUserId,
                disposition: $command->disposition,
                notes: $notes,
                idempotencyKey: $idempotencyKey,
            );
        } catch (
            RecordAccessEventManualReviewException $exception
        ) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RecordAccessEventManualReviewException(
                message: 'Não foi possível registrar a análise manual do evento.',
                previous: $exception,
            );
        }

        if (! $result instanceof RecordAccessEventManualReviewResult) {
            throw new RecordAccessEventManualReviewException(
                'O evento de acesso não foi encontrado.'
            );
        }

        return $result;
    }
}
