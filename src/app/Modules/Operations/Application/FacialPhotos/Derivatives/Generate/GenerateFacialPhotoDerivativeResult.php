<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use InvalidArgumentException;

final readonly class GenerateFacialPhotoDerivativeResult
{
    public function __construct(
        public string $derivativeId,
        public ?string $attemptId,
        public FacialPhotoDerivativeStatus $status,
        public bool $reused,
        public int $mediaId,
        public int $width,
        public int $height,
        public string $mimeType,
        public int $sizeBytes,
        public string $sha256,
    ) {
        if ($this->status !== FacialPhotoDerivativeStatus::Ready) {
            throw new InvalidArgumentException(
                'O resultado da derivação deve estar pronto.'
            );
        }

        foreach (
            array_filter([
                $this->derivativeId,
                $this->attemptId,
            ]) as $identifier
        ) {
            if (
                preg_match(
                    '/\A[0-9a-f-]{36}\z/',
                    (string) $identifier
                ) !== 1
            ) {
                throw new InvalidArgumentException(
                    'O identificador da derivação é inválido.'
                );
            }
        }

        if (
            $this->mediaId < 1
            || $this->width < 1
            || $this->height < 1
            || $this->sizeBytes < 1
        ) {
            throw new InvalidArgumentException(
                'Os metadados da derivação são inválidos.'
            );
        }

        if ($this->mimeType !== 'image/jpeg') {
            throw new InvalidArgumentException(
                'A derivação deve utilizar JPEG.'
            );
        }

        if (
            preg_match(
                '/\A[a-f0-9]{64}\z/',
                $this->sha256
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'A assinatura da derivação é inválida.'
            );
        }
    }

    /**
     * @return array{
     *     width: int,
     *     height: int,
     *     mime_type: string,
     *     size_bytes: int,
     *     sha256: string
     * }
     */
    public function outputMetadata(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'mime_type' => $this->mimeType,
            'size_bytes' => $this->sizeBytes,
            'sha256' => $this->sha256,
        ];
    }
}
