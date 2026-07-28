<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Preview\Receipts;

use RuntimeException;
use Throwable;

final class FacialPhotoPreviewReceiptException extends RuntimeException
{
    public static function issuanceFailed(
        Throwable $previous
    ): self {
        return new self(
            'Não foi possível preparar a confirmação temporária da foto.',
            previous: $previous
        );
    }

    public static function invalid(
        ?Throwable $previous = null
    ): self {
        return new self(
            'A confirmação temporária da foto é inválida ou expirou. '
                .'Analise a imagem novamente.',
            previous: $previous
        );
    }
}
