<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Process;

use Throwable;

final readonly class ProcessAccessEventUseCase
{
    public function __construct(
        private ProcessAccessEventRepository $repository,
    ) {}

    public function execute(
        ProcessAccessEventCommand $command
    ): ProcessAccessEventResult {
        $eventId = trim(
            $command->eventId
        );

        if ($eventId === '') {
            throw new ProcessAccessEventException(
                'O identificador do evento é obrigatório.'
            );
        }

        if (mb_strlen($eventId) > 36) {
            throw new ProcessAccessEventException(
                'O identificador do evento excede o tamanho permitido.'
            );
        }

        try {
            $result = $this->repository->process(
                $eventId
            );
        } catch (Throwable $exception) {
            try {
                $this->repository->markFailed(
                    $eventId,
                    'Falha inesperada durante o processamento controlado do evento.'
                );
            } catch (Throwable) {
                // A falha original deve permanecer como causa principal.
            }

            throw new ProcessAccessEventException(
                message: 'Não foi possível processar o evento de acesso.',
                previous: $exception,
            );
        }

        if ($result === null) {
            throw new ProcessAccessEventException(
                'O evento de acesso não foi encontrado.'
            );
        }

        return $result;
    }
}
