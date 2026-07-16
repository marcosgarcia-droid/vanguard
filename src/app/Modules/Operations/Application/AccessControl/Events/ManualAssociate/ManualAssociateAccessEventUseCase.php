<?php

namespace App\Modules\Operations\Application\AccessControl\Events\ManualAssociate;

use Throwable;

final readonly class ManualAssociateAccessEventUseCase
{
    private const MAX_IDENTIFIER_LENGTH = 36;

    private const MAX_IDEMPOTENCY_KEY_LENGTH = 64;

    private const MAX_REASON_LENGTH = 1000;

    public function __construct(
        private ManualAssociateAccessEventRepository $repository,
    ) {}

    public function execute(
        ManualAssociateAccessEventCommand $command
    ): ManualAssociateAccessEventResult {
        $eventId = $this->requiredIdentifier(
            $command->eventId,
            'O identificador do evento é obrigatório.',
            'O identificador do evento excede o tamanho permitido.',
        );

        $visitorId = $this->requiredIdentifier(
            $command->visitorId,
            'O identificador do visitante é obrigatório.',
            'O identificador do visitante excede o tamanho permitido.',
        );

        $visitId = $this->optionalIdentifier(
            $command->visitId,
            'O identificador da visita excede o tamanho permitido.',
        );

        if ($command->operatorUserId <= 0) {
            throw new ManualAssociateAccessEventException(
                'O operador responsável é obrigatório.'
            );
        }

        $reason = trim($command->reason);

        if ($reason === '') {
            throw new ManualAssociateAccessEventException(
                'A justificativa da associação manual é obrigatória.'
            );
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw new ManualAssociateAccessEventException(
                'A justificativa excede o tamanho permitido.'
            );
        }

        $idempotencyKey = trim(
            $command->idempotencyKey
        );

        if ($idempotencyKey === '') {
            throw new ManualAssociateAccessEventException(
                'A chave de idempotência é obrigatória.'
            );
        }

        if (
            mb_strlen($idempotencyKey)
            > self::MAX_IDEMPOTENCY_KEY_LENGTH
        ) {
            throw new ManualAssociateAccessEventException(
                'A chave de idempotência excede o tamanho permitido.'
            );
        }

        try {
            $result = $this->repository->associate(
                eventId: $eventId,
                visitorId: $visitorId,
                visitId: $visitId,
                operatorUserId: $command->operatorUserId,
                reason: $reason,
                idempotencyKey: $idempotencyKey,
            );
        } catch (
            ManualAssociateAccessEventException $exception
        ) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ManualAssociateAccessEventException(
                message: 'Não foi possível registrar a associação manual do evento.',
                previous: $exception,
            );
        }

        if ($result === null) {
            throw new ManualAssociateAccessEventException(
                'O evento de acesso não foi encontrado.'
            );
        }

        return $result;
    }

    private function requiredIdentifier(
        string $value,
        string $requiredMessage,
        string $lengthMessage,
    ): string {
        $value = trim($value);

        if ($value === '') {
            throw new ManualAssociateAccessEventException(
                $requiredMessage
            );
        }

        if (
            mb_strlen($value)
            > self::MAX_IDENTIFIER_LENGTH
        ) {
            throw new ManualAssociateAccessEventException(
                $lengthMessage
            );
        }

        return $value;
    }

    private function optionalIdentifier(
        ?string $value,
        string $lengthMessage,
    ): ?string {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (
            mb_strlen($value)
            > self::MAX_IDENTIFIER_LENGTH
        ) {
            throw new ManualAssociateAccessEventException(
                $lengthMessage
            );
        }

        return $value;
    }
}
