<?php

namespace App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation;

use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowCommand;
use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowException;
use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowUseCase;
use App\Support\Contracts\UseCase;
use Throwable;

final readonly class ContinueManuallyAssociatedAccessEventFlowUseCase implements UseCase
{
    private const MAX_IDENTIFIER_LENGTH = 36;

    public function __construct(
        private ContinueManuallyAssociatedAccessEventFlowRepository $repository,
        private ContinueAccessEventFlowUseCase $continueFlow,
    ) {}

    public function execute(
        ContinueManuallyAssociatedAccessEventFlowCommand $command
    ): ContinueManuallyAssociatedAccessEventFlowResult {
        $eventId = trim($command->eventId);

        if ($eventId === '') {
            throw new ContinueManuallyAssociatedAccessEventFlowException(
                'O identificador do evento é obrigatório para continuar o fluxo após a associação manual.'
            );
        }

        if (
            mb_strlen($eventId)
            > self::MAX_IDENTIFIER_LENGTH
        ) {
            throw new ContinueManuallyAssociatedAccessEventFlowException(
                'O identificador do evento excede o tamanho permitido.'
            );
        }

        if ($command->operatorUserId <= 0) {
            throw new ContinueManuallyAssociatedAccessEventFlowException(
                'O operador responsável é obrigatório.'
            );
        }

        try {
            $context = $this->repository->prepare(
                eventId: $eventId,
                operatorUserId: $command->operatorUserId,
            );
        } catch (
            ContinueManuallyAssociatedAccessEventFlowException $exception
        ) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ContinueManuallyAssociatedAccessEventFlowException(
                message: 'Não foi possível validar a associação manual antes de continuar o fluxo.',
                previous: $exception,
            );
        }

        if (
            ! $context
            instanceof ContinueManuallyAssociatedAccessEventFlowContext
        ) {
            throw new ContinueManuallyAssociatedAccessEventFlowException(
                'O evento de acesso não foi encontrado.'
            );
        }

        /*
         * O guard conclui sua própria transação antes da
         * orquestração. O pipeline preserva as transações,
         * locks e idempotência de cada etapa.
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
            throw new ContinueManuallyAssociatedAccessEventFlowException(
                message: 'Não foi possível continuar o fluxo do evento associado manualmente.',
                previous: $exception,
            );
        } catch (Throwable $exception) {
            throw new ContinueManuallyAssociatedAccessEventFlowException(
                message: 'Falha inesperada durante a continuação do fluxo do evento associado manualmente.',
                previous: $exception,
            );
        }

        return new ContinueManuallyAssociatedAccessEventFlowResult(
            eventId: $context->eventId,
            associationId: $context->associationId,
            flow: $flow,
        );
    }
}
