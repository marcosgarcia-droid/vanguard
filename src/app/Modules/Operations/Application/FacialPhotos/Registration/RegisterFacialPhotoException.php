<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Registration;

use RuntimeException;
use Throwable;

final class RegisterFacialPhotoException extends RuntimeException
{
    private function __construct(
        public readonly RegisterFacialPhotoFailure $failure,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            0,
            $previous
        );
    }

    public static function invalidSubject(): self
    {
        return new self(
            failure: RegisterFacialPhotoFailure::InvalidSubject,
            message: 'A pessoa informada para a foto facial é inválida.',
        );
    }

    public static function subjectNotFound(): self
    {
        return new self(
            failure: RegisterFacialPhotoFailure::SubjectNotFound,
            message: 'A pessoa informada para a foto facial não foi encontrada.',
        );
    }

    public static function subjectUnavailable(): self
    {
        return new self(
            failure: RegisterFacialPhotoFailure::SubjectUnavailable,
            message: 'A pessoa informada não está disponível para cadastro facial.',
        );
    }

    public static function sourceFileUnavailable(): self
    {
        return new self(
            failure: RegisterFacialPhotoFailure::SourceFileUnavailable,
            message: 'O arquivo original da foto facial não está disponível.',
        );
    }

    public static function invalidExpectedFingerprint(): self
    {
        return new self(
            failure: RegisterFacialPhotoFailure::InvalidExpectedFingerprint,
            message: 'A confirmação da foto facial não possui uma assinatura válida. '
                .'Analise a imagem novamente.',
        );
    }

    public static function definitiveFingerprintUnavailable(): self
    {
        return new self(
            failure: RegisterFacialPhotoFailure::DefinitiveFingerprintUnavailable,
            message: 'Não foi possível confirmar a integridade da foto facial armazenada.',
        );
    }

    public static function definitiveFingerprintMismatch(): self
    {
        return new self(
            failure: RegisterFacialPhotoFailure::DefinitiveFingerprintMismatch,
            message: 'A foto facial armazenada não corresponde à imagem confirmada. '
                .'Capture ou selecione a foto novamente.',
        );
    }

    public static function invalidConfirmationProof(): self
    {
        return new self(
            failure: RegisterFacialPhotoFailure::InvalidConfirmationProof,
            message: 'A confirmação da foto facial não é válida. '
                .'Analise a imagem novamente.',
        );
    }

    public static function confirmationAlreadyConsumed(
        ?Throwable $previous = null
    ): self {
        return new self(
            failure: RegisterFacialPhotoFailure::ConfirmationAlreadyConsumed,
            message: 'Esta confirmação da foto facial já foi utilizada. '
                .'Analise ou capture a imagem novamente.',
            previous: $previous,
        );
    }

    public function isConfirmationAlreadyConsumed(): bool
    {
        return $this->failure
            === RegisterFacialPhotoFailure::ConfirmationAlreadyConsumed;
    }

    public static function registrationFailed(
        ?Throwable $previous = null
    ): self {
        return new self(
            failure: RegisterFacialPhotoFailure::RegistrationFailed,
            message: 'Não foi possível registrar e analisar a foto facial.',
            previous: $previous,
        );
    }
}
