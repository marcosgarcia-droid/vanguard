<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Reprocess;

use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowCommand;
use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowException;
use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowUseCase;
use App\Support\Contracts\UseCase;
use Illuminate\Support\Str;
use Throwable;

final readonly class ReprocessAccessEventFlowUseCase implements UseCase
{
    private const MAX_IDENTIFIER_LENGTH = 36;

    public function __construct(
        private ReprocessAccessEventFlowRepository $repository,
        private ContinueAccessEventFlowUseCase $continueFlow,
    ) {}

    public function execute(
        ReprocessAccessEventFlowCommand $command
    ): ReprocessAccessEventFlowResult {
        $eventId = trim($command->eventId);

        if ($eventId === '') {
            throw new ReprocessAccessEventFlowException(
                'O identificador do evento é obrigatório para reprocessar o fluxo.'
            );
        }

        if (
            mb_strlen($eventId)
            > self::MAX_IDENTIFIER_LENGTH
        ) {
            throw new ReprocessAccessEventFlowException(
                'O identificador do evento excede o tamanho permitido.'
            );
        }

        if ($command->operatorUserId <= 0) {
            throw new ReprocessAccessEventFlowException(
                'O operador responsável é obrigatório.'
            );
        }

        $idempotencyKey = trim(
            $command->idempotencyKey
        );

        if (! Str::isUuid($idempotencyKey)) {
            throw new ReprocessAccessEventFlowException(
                'A chave de idempotência do reprocessamento é inválida.'
            );
        }

        try {
            $context = $this->repository->prepare(
                eventId: $eventId,
                operatorUserId: $command->operatorUserId,
                idempotencyKey: $idempotencyKey,
            );
        } catch (
            ReprocessAccessEventFlowException $exception
        ) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ReprocessAccessEventFlowException(
                message: 'Não foi possível validar o evento antes do reprocessamento.',
                previous: $exception,
            );
        }

        if (
            ! $context
            instanceof ReprocessAccessEventFlowContext
        ) {
            throw new ReprocessAccessEventFlowException(
                'O evento de acesso não foi encontrado.'
            );
        }

        /*
         * Quando há liberação humana, o consumo foi
         * persistido antes desta etapa. Ele representa o
         * uso único da autorização, não o sucesso do fluxo.
         */
        try {
            $flow = $this->continueFlow->execute(
                new ContinueAccessEventFlowCommand(
                    eventId: $context->eventId,
                )
            );
        } catch (
            ContinueAccessEventFlowException $exception
        ) {
            throw new ReprocessAccessEventFlowException(
                message: $exception->getMessage()
                    .$this->consumedReleaseSuffix(
                        $context
                    ),

                previous: $exception,

                manualReviewReleaseConsumed: $context->manualReviewReleaseUsed,

                manualReviewId: $context->manualReviewId,

                manualReviewConsumptionId: $context->manualReviewConsumptionId,
            );
        } catch (Throwable $exception) {
            throw new ReprocessAccessEventFlowException(
                message: 'Não foi possível reprocessar o fluxo do evento.'
                    .$this->consumedReleaseSuffix(
                        $context
                    ),

                previous: $exception,

                manualReviewReleaseConsumed: $context->manualReviewReleaseUsed,

                manualReviewId: $context->manualReviewId,

                manualReviewConsumptionId: $context->manualReviewConsumptionId,
            );
        }

        return new ReprocessAccessEventFlowResult(
            flow: $flow,

            manualReviewReleaseUsed: $context->manualReviewReleaseUsed,

            decisionId: $context->decisionId,

            manualReviewId: $context->manualReviewId,

            manualReviewConsumptionId: $context->manualReviewConsumptionId,
        );
    }

    private function consumedReleaseSuffix(
        ReprocessAccessEventFlowContext $context
    ): string {
        return $context->manualReviewReleaseUsed
            ? ' A liberação manual foi consumida e uma nova análise será necessária para outra tentativa.'
            : '';
    }
}
