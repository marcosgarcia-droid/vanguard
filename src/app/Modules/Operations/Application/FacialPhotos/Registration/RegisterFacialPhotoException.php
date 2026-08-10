<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Registration;

use RuntimeException;
use Throwable;

final class RegisterFacialPhotoException extends RuntimeException
{
    private bool $confirmationAlreadyConsumed = false;

    public static function invalidSubject(): self
    {
        return new self(
            'A pessoa informada para a foto facial é inválida.'
        );
    }

    public static function subjectNotFound(): self
    {
        return new self(
            'A pessoa informada para a foto facial não foi encontrada.'
        );
    }

    public static function subjectUnavailable(): self
    {
        return new self(
            'A pessoa informada não está disponível para cadastro facial.'
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

    public static function invalidConfirmationProof(): self
    {
        return new self(
            'A confirmação da foto facial não é válida. '
            .'Analise a imagem novamente.'
        );
    }

    public static function confirmationAlreadyConsumed(
        ?Throwable $previous = null
    ): self {
        $exception = new self(
            'Esta confirmação da foto facial já foi utilizada. '
            .'Analise ou capture a imagem novamente.',
            previous: $previous
        );

        $exception->confirmationAlreadyConsumed = true;

        return $exception;
    }

    public function isConfirmationAlreadyConsumed(): bool
    {
        return $this->confirmationAlreadyConsumed;
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
