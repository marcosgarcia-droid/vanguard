<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Decide;

use App\Modules\Operations\Application\AccessControl\AccessControlRuntime;
use App\Support\Contracts\UseCase;
use Throwable;

final readonly class DecideAccessEventUseCase implements UseCase
{
    public function __construct(
        private DecideAccessEventRepository $repository,
        private AccessControlRuntime $runtime,
    ) {}

    public function execute(
        DecideAccessEventCommand $command
    ): DecideAccessEventResult {
        $eventId = trim(
            $command->eventId
        );

        if ($eventId === '') {
            throw new DecideAccessEventException(
                'O identificador do evento é obrigatório.'
            );
        }

        if (mb_strlen($eventId) > 36) {
            throw new DecideAccessEventException(
                'O identificador do evento excede o tamanho permitido.'
            );
        }

        try {
            $result = $this->repository->decide(
                eventId: $eventId,
                automaticExecutionAllowed: $this->runtime
                    ->allowsAutomaticVisitOperations(),
            );
        } catch (Throwable $exception) {
            throw new DecideAccessEventException(
                message: 'Não foi possível calcular a decisão operacional do evento.',
                previous: $exception,
            );
        }

        if ($result === null) {
            throw new DecideAccessEventException(
                'O evento de acesso não foi encontrado.'
            );
        }

        return $result;
    }
}
