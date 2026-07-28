<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Registration;

use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RegisterVisitorFacialPhotoExceptionTest extends TestCase
{
    public function test_it_exposes_safe_registration_messages(): void
    {
        $this->assertSame(
            'O visitante informado para a foto facial não foi encontrado.',
            RegisterVisitorFacialPhotoException::visitorNotFound()
                ->getMessage()
        );

        $this->assertSame(
            'O arquivo original da foto facial não está disponível.',
            RegisterVisitorFacialPhotoException::sourceFileUnavailable()
                ->getMessage()
        );

        $this->assertSame(
            'A confirmação da foto facial não possui uma assinatura válida. '
                .'Analise a imagem novamente.',
            RegisterVisitorFacialPhotoException::invalidExpectedFingerprint()
                ->getMessage()
        );

        $this->assertSame(
            'Não foi possível confirmar a integridade da foto facial armazenada.',
            RegisterVisitorFacialPhotoException::definitiveFingerprintUnavailable()
                ->getMessage()
        );

        $this->assertSame(
            'A foto facial armazenada não corresponde à imagem confirmada. '
                .'Capture ou selecione a foto novamente.',
            RegisterVisitorFacialPhotoException::definitiveFingerprintMismatch()
                ->getMessage()
        );

        $previous = new RuntimeException(
            'detalhe interno'
        );

        $exception =
            RegisterVisitorFacialPhotoException::registrationFailed(
                $previous
            );

        $this->assertSame(
            'Não foi possível registrar e analisar a foto facial.',
            $exception->getMessage()
        );

        $this->assertSame(
            $previous,
            $exception->getPrevious()
        );
    }
}
