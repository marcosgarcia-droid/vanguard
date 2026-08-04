<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Normalization;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeProfile;
use InvalidArgumentException;

final readonly class FacialPhotoNormalizationResult
{
    public function __construct(
        public string $absolutePath,
        public FacialPhotoDerivativeProfile $profile,
        public string $policyVersion,
        public string $normalizer,
        public string $normalizerVersion,
        public string $sourceSha256,
        public int $width,
        public int $height,
        public string $mimeType,
        public int $sizeBytes,
        public string $sha256
    ) {
        $this->validate();
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

    private function validate(): void
    {
        if (
            $this->absolutePath === ''
            || ! str_starts_with(
                $this->absolutePath,
                DIRECTORY_SEPARATOR
            )
        ) {
            throw new InvalidArgumentException(
                'O caminho do artefato normalizado deve ser absoluto.'
            );
        }

        $this->validateToken(
            $this->policyVersion,
            'A versão da política de normalização',
            50
        );

        $this->validateToken(
            $this->normalizer,
            'O identificador do normalizador',
            100
        );

        $this->validateToken(
            $this->normalizerVersion,
            'A versão do normalizador',
            50
        );

        $this->validateSha256(
            $this->sourceSha256,
            'A assinatura da imagem original'
        );

        $this->validateSha256(
            $this->sha256,
            'A assinatura do artefato normalizado'
        );

        if ($this->width < 1 || $this->height < 1) {
            throw new InvalidArgumentException(
                'As dimensões do artefato normalizado são inválidas.'
            );
        }

        if ($this->mimeType !== 'image/jpeg') {
            throw new InvalidArgumentException(
                'O artefato normalizado deve utilizar JPEG.'
            );
        }

        if ($this->sizeBytes < 1) {
            throw new InvalidArgumentException(
                'O tamanho do artefato normalizado é inválido.'
            );
        }
    }

    private function validateToken(
        string $value,
        string $field,
        int $maximumLength
    ): void {
        if (
            mb_strlen($value) > $maximumLength
            || preg_match(
                '/\A[a-z0-9][a-z0-9._-]*\z/',
                $value
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                "{$field} é inválida."
            );
        }
    }

    private function validateSha256(
        string $value,
        string $field
    ): void {
        if (
            preg_match(
                '/\A[a-f0-9]{64}\z/',
                $value
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                "{$field} é inválida."
            );
        }
    }
}
