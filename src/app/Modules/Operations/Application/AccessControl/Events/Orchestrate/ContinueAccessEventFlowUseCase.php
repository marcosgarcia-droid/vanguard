<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Orchestrate;

use App\Modules\Operations\Application\AccessControl\Events\Decide\DecideAccessEventCommand;
use App\Modules\Operations\Application\AccessControl\Events\Decide\DecideAccessEventUseCase;
use App\Modules\Operations\Application\AccessControl\Events\Execute\ExecuteAccessEventOperationalExecutionCommand;
use App\Modules\Operations\Application\AccessControl\Events\Execute\ExecuteAccessEventOperationalExecutionUseCase;
use App\Modules\Operations\Application\AccessControl\Events\Execute\RegisterAccessEventOperationalExecutionCommand;
use App\Modules\Operations\Application\AccessControl\Events\Execute\RegisterAccessEventOperationalExecutionUseCase;
use App\Modules\Operations\Application\AccessControl\Events\Process\ProcessAccessEventCommand;
use App\Modules\Operations\Application\AccessControl\Events\Process\ProcessAccessEventUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Support\Contracts\UseCase;
use Throwable;

final readonly class ContinueAccessEventFlowUseCase implements UseCase
{
    public function __construct(
        private ProcessAccessEventUseCase $processEvent,
        private DecideAccessEventUseCase $decideEvent,
        private RegisterAccessEventOperationalExecutionUseCase $registerExecution,
        private ExecuteAccessEventOperationalExecutionUseCase $executeOperation,
    ) {}

    public function execute(
        ContinueAccessEventFlowCommand $command
    ): ContinueAccessEventFlowResult {
        $eventId = trim(
            $command->eventId
        );

        if ($eventId === '') {
            throw new ContinueAccessEventFlowException(
                'O identificador do evento é obrigatório para continuar o fluxo operacional.'
            );
        }

        if (mb_strlen($eventId) > 36) {
            throw new ContinueAccessEventFlowException(
                'O identificador do evento excede o tamanho permitido.'
            );
        }

        /*
         * Cada etapa mantém sua própria transação, idempotência e
         * histórico. O coordenador não cria uma transação abrangente,
         * permitindo retomar o fluxo a partir do evento persistido.
         */
        try {
            $processing = $this->processEvent->execute(
                new ProcessAccessEventCommand(
                    eventId: $eventId,
                )
            );
        } catch (Throwable $exception) {
            throw new ContinueAccessEventFlowException(
                message: 'Não foi possível processar o evento durante a continuação do fluxo operacional.',
                previous: $exception,
            );
        }

        try {
            $decision = $this->decideEvent->execute(
                new DecideAccessEventCommand(
                    eventId: $eventId,
                )
            );
        } catch (Throwable $exception) {
            throw new ContinueAccessEventFlowException(
                message: 'Não foi possível calcular a decisão durante a continuação do fluxo operacional.',
                previous: $exception,
            );
        }

        try {
            $registration =
                $this->registerExecution->execute(
                    new RegisterAccessEventOperationalExecutionCommand(
                        decisionId: $decision->decisionId,
                    )
                );
        } catch (Throwable $exception) {
            throw new ContinueAccessEventFlowException(
                message: 'Não foi possível registrar a tentativa durante a continuação do fluxo operacional.',
                previous: $exception,
            );
        }

        $execution = null;

        /*
         * Tentativas bloqueadas, ignoradas ou finalizadas permanecem
         * somente no ledger. Apenas uma tentativa Pending pode chegar
         * ao executor interno controlado.
         */
        if (
            $registration->status
            === AccessEventOperationalExecutionStatus::Pending
        ) {
            try {
                $execution =
                    $this->executeOperation->execute(
                        new ExecuteAccessEventOperationalExecutionCommand(
                            executionId: $registration->executionId,
                        )
                    );
            } catch (Throwable $exception) {
                throw new ContinueAccessEventFlowException(
                    message: 'Não foi possível executar a operação durante a continuação do fluxo operacional.',
                    previous: $exception,
                );
            }
        }

        return new ContinueAccessEventFlowResult(
            eventId: $eventId,
            processing: $processing,
            decision: $decision,
            registration: $registration,
            execution: $execution,
        );
    }
}
