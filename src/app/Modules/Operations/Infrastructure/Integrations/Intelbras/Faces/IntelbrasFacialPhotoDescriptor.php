<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use InvalidArgumentException;

final readonly class IntelbrasFacialPhotoDescriptor
{
    public const MAX_BYTES = 102_400;

    public const MIN_WIDTH = 150;

    public const MAX_WIDTH = 600;

    public const MIN_HEIGHT = 300;

    public const MAX_HEIGHT = 1_200;

    private string $base64;

    public string $sha256;

    public function __construct(
        string $base64,
        public int $byteLength,
        public int $width,
        public int $height,
    ) {
        $normalizedBase64 = trim($base64);

        if (
            $normalizedBase64 === ''
            || preg_match('/\s/', $normalizedBase64) === 1
        ) {
            throw new InvalidArgumentException(
                'A foto facial deve possuir Base64 canônico sem espaços.'
            );
        }

        $decoded = base64_decode(
            $normalizedBase64,
            true
        );

        if (
            $decoded === false
            || base64_encode($decoded) !== $normalizedBase64
        ) {
            throw new InvalidArgumentException(
                'A foto facial possui Base64 inválido.'
            );
        }

        $actualByteLength = strlen($decoded);

        if (
            $actualByteLength < 4
            || $actualByteLength !== $byteLength
            || $actualByteLength > self::MAX_BYTES
        ) {
            throw new InvalidArgumentException(
                'O tamanho da foto facial é inválido.'
            );
        }

        if (
            ! str_starts_with($decoded, "\xFF\xD8")
            || ! str_ends_with($decoded, "\xFF\xD9")
        ) {
            throw new InvalidArgumentException(
                'A derivada facial deve usar o formato JPEG.'
            );
        }

        if (
            $width < self::MIN_WIDTH
            || $width > self::MAX_WIDTH
            || $height < self::MIN_HEIGHT
            || $height > self::MAX_HEIGHT
        ) {
            throw new InvalidArgumentException(
                'As dimensões da foto facial estão fora dos limites Intelbras.'
            );
        }

        if ($height > ($width * 2)) {
            throw new InvalidArgumentException(
                'A altura da foto não pode exceder duas vezes a largura.'
            );
        }

        $this->base64 = $normalizedBase64;
        $this->sha256 = hash(
            'sha256',
            $decoded
        );
    }

    public function transportBase64(): string
    {
        return $this->base64;
    }

    /**
     * @return array{
     *     byte_length: int,
     *     width: int,
     *     height: int,
     *     sha256: string
     * }
     */
    public function toSafeArray(): array
    {
        return [
            'byte_length' => $this->byteLength,
            'width' => $this->width,
            'height' => $this->height,
            'sha256' => $this->sha256,
        ];
    }
}
