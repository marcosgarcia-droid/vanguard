<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Execute;

use App\Modules\Operations\Application\AccessControl\AccessControlRuntime;
use App\Support\Contracts\UseCase;
use Throwable;

final readonly class ExecuteAccessEventOperationalExecutionUseCase implements UseCase
{
    public function __construct(
        private ExecuteAccessEventOperationalExecutionRepository $repository,
        private AccessControlRuntime $runtime,
    ) {}

    public function execute(
        ExecuteAccessEventOperationalExecutionCommand $command
    ): ExecuteAccessEventOperationalExecutionResult {
        $executionId = trim(
            $command->executionId
        );

        if ($executionId === '') {
            throw new ExecuteAccessEventOperationalExecutionException(
                'O identificador da tentativa operacional é obrigatório.'
            );
        }

        if (mb_strlen($executionId) > 36) {
            throw new ExecuteAccessEventOperationalExecutionException(
                'O identificador da tentativa operacional excede o tamanho permitido.'
            );
        }

        try {
            $result =
                $this->repository
                    ->executeAutomaticAttempt(
                        executionId: $executionId,
                        automaticExecutionAllowed: $this->runtime
                            ->allowsAutomaticVisitOperations(),
                    );
        } catch (Throwable $exception) {
            try {
                $this->repository->markFailed(
                    $executionId
                );
            } catch (Throwable) {
                /*
                 * A falha principal não deve ser ocultada por uma
                 * eventual indisponibilidade ao registrar o erro.
                 */
            }

            throw new ExecuteAccessEventOperationalExecutionException(
                message: 'Não foi possível executar a tentativa operacional.',
                previous: $exception,
            );
        }

        if ($result === null) {
            throw new ExecuteAccessEventOperationalExecutionException(
                'A tentativa operacional informada não foi encontrada.'
            );
        }

        return $result;
    }
}
