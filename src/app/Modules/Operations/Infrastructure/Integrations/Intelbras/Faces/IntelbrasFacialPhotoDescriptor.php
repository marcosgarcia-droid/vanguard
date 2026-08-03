<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use InvalidArgumentException;

final readonly class IntelbrasFacialPhotoDescriptor
{
    public const MIME_TYPE = 'image/jpeg';

    public const MAX_BYTES = 100_000;

    public const MIN_WIDTH = 150;

    public const MIN_HEIGHT = 300;

    public const MAX_WIDTH = 600;

    public const MAX_HEIGHT = 1_200;

    public string $sha256;

    public string $mimeType;

    public function __construct(
        string $sha256,
        public int $byteLength,
        public int $width,
        public int $height,
        string $mimeType = self::MIME_TYPE,
    ) {
        $normalizedSha256 = strtolower(trim($sha256));

        if (
            preg_match(
                '/^[a-f0-9]{64}$/D',
                $normalizedSha256
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'O SHA-256 da foto facial é inválido.'
            );
        }

        if (
            $byteLength < 1
            || $byteLength > self::MAX_BYTES
        ) {
            throw new InvalidArgumentException(
                'O tamanho da foto facial é inválido.'
            );
        }

        if (
            $width < self::MIN_WIDTH
            || $width > self::MAX_WIDTH
            || $height < self::MIN_HEIGHT
            || $height > self::MAX_HEIGHT
            || $height > ($width * 2)
        ) {
            throw new InvalidArgumentException(
                'As dimensões da foto facial são inválidas.'
            );
        }

        $normalizedMimeType = strtolower(
            trim($mimeType)
        );

        if ($normalizedMimeType !== self::MIME_TYPE) {
            throw new InvalidArgumentException(
                'O formato da foto facial não é suportado.'
            );
        }

        $this->sha256 = $normalizedSha256;
        $this->mimeType = $normalizedMimeType;
    }

    /**
     * @return array{
     *     sha256: string,
     *     byte_length: int,
     *     width: int,
     *     height: int,
     *     mime_type: string
     * }
     */
    public function fingerprintMaterial(): array
    {
        return [
            'sha256' => $this->sha256,
            'byte_length' => $this->byteLength,
            'width' => $this->width,
            'height' => $this->height,
            'mime_type' => $this->mimeType,
        ];
    }
}
