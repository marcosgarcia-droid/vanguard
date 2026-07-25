<?php

namespace App\Modules\Operations\Domain\FacialPhotos;

use RuntimeException;

final class FacialPhotoStatusTransitionException extends RuntimeException
{
    public static function statusNotEligible(
        FacialPhotoStatus $status
    ): self {
        return new self(
            sprintf(
                'A foto facial com situação “%s” não pode ser validada novamente.',
                $status->label()
            )
        );
    }
}
