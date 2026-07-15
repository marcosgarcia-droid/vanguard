<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Execute;

use App\Modules\Operations\Application\AccessControl\AccessControlRuntime;
use App\Support\Contracts\UseCase;
use Throwable;

final readonly class RegisterAccessEventOperationalExecutionUseCase implements UseCase
{
    public function __construct(
        private RegisterAccessEventOperationalExecutionRepository $repository,
        private AccessControlRuntime $runtime,
    ) {}

    public function execute(
        RegisterAccessEventOperationalExecutionCommand $command
    ): RegisterAccessEventOperationalExecutionResult {
        $decisionId = trim(
            $command->decisionId
        );

        if ($decisionId === '') {
            throw new RegisterAccessEventOperationalExecutionException(
                'O identificador da decisão operacional é obrigatório.'
            );
        }

        if (mb_strlen($decisionId) > 36) {
            throw new RegisterAccessEventOperationalExecutionException(
                'O identificador da decisão operacional excede o tamanho permitido.'
            );
        }

        try {
            $result =
                $this->repository
                    ->registerAutomaticAttempt(
                        decisionId: $decisionId,
                        automaticExecutionAllowed: $this->runtime
                            ->allowsAutomaticVisitOperations(),
                    );
        } catch (Throwable $exception) {
            throw new RegisterAccessEventOperationalExecutionException(
                message: 'Não foi possível registrar a tentativa de execução operacional.',
                previous: $exception,
            );
        }

        if ($result === null) {
            throw new RegisterAccessEventOperationalExecutionException(
                'A decisão operacional informada não foi encontrada.'
            );
        }

        return $result;
    }
}
