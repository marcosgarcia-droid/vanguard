<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Reprocess;

use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowCommand;
use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowException;
use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowResult;
use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowUseCase;
use App\Support\Contracts\UseCase;
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
    ): ContinueAccessEventFlowResult {
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

        try {
            $context = $this->repository->prepare(
                eventId: $eventId,
                operatorUserId: $command->operatorUserId,
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
         * O guard conclui sua transação antes da orquestração.
         * O coordenador preserva as transações, locks e
         * idempotência próprios de cada etapa operacional.
         */
        try {
            return $this->continueFlow->execute(
                new ContinueAccessEventFlowCommand(
                    eventId: $context->eventId,
                )
            );
        } catch (
            ContinueAccessEventFlowException $exception
        ) {
            throw new ReprocessAccessEventFlowException(
                message: $exception->getMessage(),
                previous: $exception,
            );
        } catch (Throwable $exception) {
            throw new ReprocessAccessEventFlowException(
                message: 'Não foi possível reprocessar o fluxo do evento.',
                previous: $exception,
            );
        }
    }
}
