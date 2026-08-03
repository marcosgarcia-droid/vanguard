<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use InvalidArgumentException;

final readonly class IntelbrasFacialCredentialItem
{
    public const MAX_EXTERNAL_ID_BYTES = 64;

    public const MAX_DISPLAY_NAME_BYTES = 120;

    public string $externalUserId;

    public ?string $displayName;

    public function __construct(
        string $externalUserId,
        public IntelbrasFacialPhotoDescriptor $photo,
        ?string $displayName = null,
    ) {
        $normalizedExternalUserId = trim(
            $externalUserId
        );

        if (
            strlen($normalizedExternalUserId)
                > self::MAX_EXTERNAL_ID_BYTES
            || preg_match(
                '/^[A-Za-z0-9._:-]+$/D',
                $normalizedExternalUserId
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'O identificador externo da credencial facial é inválido.'
            );
        }

        $normalizedDisplayName = $displayName === null
            ? null
            : trim($displayName);

        if ($normalizedDisplayName === '') {
            $normalizedDisplayName = null;
        }

        if (
            $normalizedDisplayName !== null
            && (
                strlen($normalizedDisplayName)
                    > self::MAX_DISPLAY_NAME_BYTES
                || preg_match(
                    '/[\x00-\x1F\x7F]/',
                    $normalizedDisplayName
                ) === 1
            )
        ) {
            throw new InvalidArgumentException(
                'O nome da credencial facial é inválido.'
            );
        }

        $this->externalUserId =
            $normalizedExternalUserId;

        $this->displayName =
            $normalizedDisplayName;
    }

    public function hasDisplayName(): bool
    {
        return $this->displayName !== null;
    }

    /**
     * @return array{
     *     external_user_id: string,
     *     display_name: ?string,
     *     photo: array{
     *         sha256: string,
     *         byte_length: int,
     *         width: int,
     *         height: int,
     *         mime_type: string
     *     }
     * }
     */
    public function fingerprintMaterial(): array
    {
        return [
            'external_user_id' => $this->externalUserId,
            'display_name' => $this->displayName,
            'photo' => $this->photo->fingerprintMaterial(),
        ];
    }
}
