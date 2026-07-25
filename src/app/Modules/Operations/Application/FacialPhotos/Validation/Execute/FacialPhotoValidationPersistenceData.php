<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransition;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use InvalidArgumentException;

final readonly class FacialPhotoValidationPersistenceData
{
    public function __construct(
        public FacialPhotoValidationTarget $target,
        public FacialPhotoValidationResult $validation,
        public FacialPhotoStatusTransition $transition,
        public ?int $operatorUserId = null,
    ) {
        if (
            $this->transition->decision
            !== $this->validation->decision
        ) {
            throw new InvalidArgumentException(
                'A transição não corresponde ao resultado da validação facial.'
            );
        }

        if (
            $this->transition->from
            !== $this->target->status
        ) {
            throw new InvalidArgumentException(
                'A transição não parte da situação preparada para a foto facial.'
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
