<?php

namespace App\Modules\Operations\Application\FacialPhotos\Registration;

use RuntimeException;
use Throwable;

final class RegisterVisitorFacialPhotoException extends RuntimeException
{
    public static function visitorNotFound(): self
    {
        return new self(
            'O visitante informado para a foto facial não foi encontrado.'
        );
    }

    public static function sourceFileUnavailable(): self
    {
        return new self(
            'O arquivo original da foto facial não está disponível.'
        );
    }

    public static function invalidExpectedFingerprint(): self
    {
        return new self(
            'A confirmação da foto facial não possui uma assinatura válida. '
                .'Analise a imagem novamente.'
        );
    }

    public static function definitiveFingerprintUnavailable(): self
    {
        return new self(
            'Não foi possível confirmar a integridade da foto facial armazenada.'
        );
    }

    public static function definitiveFingerprintMismatch(): self
    {
        return new self(
            'A foto facial armazenada não corresponde à imagem confirmada. '
                .'Capture ou selecione a foto novamente.'
        );
    }

    public static function registrationFailed(
        ?Throwable $previous = null
    ): self {
        return new self(
            'Não foi possível registrar e analisar a foto facial.',
            previous: $previous
        );
    }
}
