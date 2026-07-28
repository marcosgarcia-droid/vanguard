<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation;

use RuntimeException;
use Throwable;

final class ConfirmFacialPhotoPreviewException extends RuntimeException
{
    public static function invalidReceipt(
        ?Throwable $previous = null
    ): self {
        return new self(
            'A confirmação temporária da foto é inválida. '
                .'Analise a imagem novamente.',
            previous: $previous
        );
    }

    public static function expiredReceipt(): self
    {
        return new self(
            'A análise temporária da foto expirou. '
                .'Analise a imagem novamente.'
        );
    }

    public static function contextMismatch(): self
    {
        return new self(
            'A confirmação da foto não corresponde a este envio. '
                .'Analise a imagem novamente.'
        );
    }

    public static function sourceFileUnavailable(): self
    {
        return new self(
            'A foto selecionada não está mais disponível. '
                .'Escolha a imagem novamente.'
        );
    }

    public static function photoChanged(): self
    {
        return new self(
            'A foto foi alterada depois da análise. '
                .'Analise a imagem novamente.'
        );
    }

    public static function photoNoLongerUsable(): self
    {
        return new self(
            'A foto não atende mais aos requisitos para uso. '
                .'Corrija ou escolha outra imagem.'
        );
    }

    public static function analysisFailed(
        Throwable $previous
    ): self {
        return new self(
            'Não foi possível confirmar a análise da foto. '
                .'Analise a imagem novamente.',
            previous: $previous
        );
    }
}
