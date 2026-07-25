<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use InvalidArgumentException;

final readonly class FacialPhotoValidationTarget
{
    public function __construct(
        public string $photoId,
        public FacialPhotoStatus $status,
        public int $mediaId,
        public string $absolutePath,
        public string $sha256,
    ) {
        if (trim($this->photoId) === '') {
            throw new InvalidArgumentException(
                'O alvo da validação facial exige uma foto.'
            );
        }

        if ($this->mediaId < 1) {
            throw new InvalidArgumentException(
                'O alvo da validação facial exige uma mídia válida.'
            );
        }

        if (
            trim($this->absolutePath) === ''
            || ! str_starts_with(
                $this->absolutePath,
                DIRECTORY_SEPARATOR
            )
        ) {
            throw new InvalidArgumentException(
                'O alvo da validação facial exige um caminho absoluto.'
            );
        }

        if (
            preg_match(
                '/\A[a-f0-9]{64}\z/i',
                $this->sha256
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'O alvo da validação facial exige um hash SHA-256 válido.'
            );
        }
    }
}
