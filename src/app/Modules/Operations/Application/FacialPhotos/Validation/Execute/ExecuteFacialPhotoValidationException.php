<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use RuntimeException;
use Throwable;

final class ExecuteFacialPhotoValidationException extends RuntimeException
{
    public static function photoNotFound(): self
    {
        return new self(
            'A foto facial informada não foi encontrada.'
        );
    }

    public static function sourceMediaUnavailable(): self
    {
        return new self(
            'O arquivo original da foto facial não está disponível para validação.'
        );
    }

    public static function sourceMediaChanged(): self
    {
        return new self(
            'O arquivo original da foto facial foi alterado durante a validação. Repita a operação.'
        );
    }

    public static function operatorNotFound(): self
    {
        return new self(
            'O operador responsável pela validação facial não foi encontrado.'
        );
    }

    public static function statusNotEligible(
        FacialPhotoStatus $status,
        ?Throwable $previous = null,
    ): self {
        return new self(
            sprintf(
                'A foto facial com situação “%s” não pode ser validada novamente.',
                $status->label()
            ),
            previous: $previous
        );
    }

    public static function targetMismatch(): self
    {
        return new self(
            'O alvo preparado não corresponde à foto facial solicitada.'
        );
    }

    public static function preparationFailed(
        ?Throwable $previous = null,
    ): self {
        return new self(
            'Não foi possível preparar a foto para validação facial.',
            previous: $previous
        );
    }

    public static function validationFailed(
        ?Throwable $previous = null,
    ): self {
        return new self(
            'Não foi possível executar a validação facial.',
            previous: $previous
        );
    }

    public static function persistenceFailed(
        ?Throwable $previous = null,
    ): self {
        return new self(
            'Não foi possível registrar o resultado da validação facial.',
            previous: $previous
        );
    }

    public static function attemptLimitReached(): self
    {
        return new self(
            'O limite de tentativas de validação desta foto facial foi atingido.'
        );
    }
}
