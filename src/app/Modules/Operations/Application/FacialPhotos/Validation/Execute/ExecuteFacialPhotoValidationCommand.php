<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use InvalidArgumentException;

final readonly class ExecuteFacialPhotoValidationCommand
{
    public function __construct(
        public string $photoId,
        public ?int $operatorUserId = null,
    ) {
        if (trim($this->photoId) === '') {
            throw new InvalidArgumentException(
                'O identificador da foto facial é obrigatório.'
            );
        }

        if (
            $this->operatorUserId !== null
            && $this->operatorUserId < 1
        ) {
            throw new InvalidArgumentException(
                'O identificador do operador facial é inválido.'
            );
        }
    }
}
