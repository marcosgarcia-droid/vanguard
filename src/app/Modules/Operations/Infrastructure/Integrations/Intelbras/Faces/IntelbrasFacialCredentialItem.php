<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use InvalidArgumentException;

final readonly class IntelbrasFacialCredentialItem
{
    public string $externalUserId;

    public ?string $displayName;

    public function __construct(
        string $externalUserId,
        public IntelbrasFacialPhotoDescriptor $photo,
        ?string $displayName = null,
    ) {
        $normalizedUserId = trim($externalUserId);

        if (
            $normalizedUserId === ''
            || strlen($normalizedUserId) > 64
            || preg_match(
                '/^[A-Za-z0-9._:-]+$/D',
                $normalizedUserId
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'O identificador externo da credencial facial é inválido.'
            );
        }

        $normalizedDisplayName = $displayName === null
            ? null
            : trim($displayName);

        if (
            $normalizedDisplayName !== null
            && (
                $normalizedDisplayName === ''
                || strlen($normalizedDisplayName) > 128
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

        $this->externalUserId = $normalizedUserId;
        $this->displayName = $normalizedDisplayName;
    }

    /**
     * @return array{
     *     external_user_id: string,
     *     display_name: ?string,
     *     photo: array{
     *         byte_length: int,
     *         width: int,
     *         height: int,
     *         sha256: string
     *     }
     * }
     */
    public function toSafeArray(): array
    {
        return [
            'external_user_id' => $this->externalUserId,
            'display_name' => $this->displayName,
            'photo' => $this->photo->toSafeArray(),
        ];
    }
}
