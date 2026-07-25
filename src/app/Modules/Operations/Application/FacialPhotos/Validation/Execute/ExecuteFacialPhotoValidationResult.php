<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransition;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ExecuteFacialPhotoValidationResult
{
    private const MAX_ATTEMPTS = 65_535;

    public function __construct(
        public string $photoId,
        public string $attemptId,
        public int $attemptNumber,
        public FacialPhotoValidationResult $validation,
        public FacialPhotoStatusTransition $transition,
        public DateTimeImmutable $validatedAt,
    ) {
        if (
            trim($this->photoId) === ''
            || trim($this->attemptId) === ''
        ) {
            throw new InvalidArgumentException(
                'O resultado da validação facial exige identificadores válidos.'
            );
        }

        if (
            $this->attemptNumber < 1
            || $this->attemptNumber > self::MAX_ATTEMPTS
        ) {
            throw new InvalidArgumentException(
                'O número da tentativa de validação facial é inválido.'
            );
        }

        if (
            $this->transition->decision
            !== $this->validation->decision
        ) {
            throw new InvalidArgumentException(
                'O resultado persistido não corresponde à transição facial.'
            );
        }
    }

    public function isApproved(): bool
    {
        return $this->validation->isApproved();
    }

    public function isRejected(): bool
    {
        return $this->validation->isRejected();
    }

    public function isInconclusive(): bool
    {
        return $this->validation->isInconclusive();
    }

    public function changedStatus(): bool
    {
        return $this->transition->changed();
    }
}
