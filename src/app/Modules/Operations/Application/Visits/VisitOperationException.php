<?php

namespace App\Modules\Operations\Application\Visits;

use App\Modules\Operations\Domain\Visits\VisitStatus;
use RuntimeException;

final class VisitOperationException extends RuntimeException
{
    public static function visitNotFound(): self
    {
        return new self('A visita informada não foi encontrada.');
    }

    public static function invalidStatus(
        string $operation,
        VisitStatus $status,
    ): self {
        return new self(
            "Não é possível {$operation} quando a visita está com status \"{$status->label()}\"."
        );
    }

    public static function authorizerUnavailable(): self
    {
        return new self(
            'A pessoa autorizadora precisa ser um funcionário ativo do mesmo grupo empresarial da visita.'
        );
    }

    public static function visitorPhotoRequired(): self
    {
        return new self(
            'O visitante precisa possuir uma foto facial antes do registro de entrada.'
        );
    }
}
