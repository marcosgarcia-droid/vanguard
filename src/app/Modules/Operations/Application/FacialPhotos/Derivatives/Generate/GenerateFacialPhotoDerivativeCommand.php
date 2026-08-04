<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeProfile;
use InvalidArgumentException;

final readonly class GenerateFacialPhotoDerivativeCommand
{
    public string $photoId;

    public FacialPhotoDerivativeProfile $profile;

    public string $policyVersion;

    public string $normalizer;

    public string $normalizerVersion;

    public ?int $requestedBy;

    public ?string $requesterName;

    public function __construct(
        string $photoId,
        string $profile,
        string $policyVersion,
        string $normalizer,
        string $normalizerVersion,
        ?int $requestedBy = null,
        ?string $requesterName = null,
    ) {
        $photoId = strtolower(
            trim($photoId)
        );

        if (
            preg_match(
                '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
                $photoId
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'O identificador da foto facial é inválido.'
            );
        }

        $this->photoId = $photoId;
        $this->profile =
            FacialPhotoDerivativeProfile::from($profile);

        $this->policyVersion = $this->token(
            $policyVersion,
            'A versão da política',
            50
        );

        $this->normalizer = $this->token(
            $normalizer,
            'O normalizador',
            100
        );

        $this->normalizerVersion = $this->token(
            $normalizerVersion,
            'A versão do normalizador',
            50
        );

        if ($requestedBy !== null && $requestedBy < 1) {
            throw new InvalidArgumentException(
                'O usuário solicitante é inválido.'
            );
        }

        $this->requestedBy = $requestedBy;

        $normalizedRequester = $requesterName === null
            ? null
            : trim($requesterName);

        if ($normalizedRequester === '') {
            $normalizedRequester = null;
        }

        if (
            $normalizedRequester !== null
            && (
                mb_strlen($normalizedRequester) > 255
                || preg_match(
                    '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
                    $normalizedRequester
                ) === 1
            )
        ) {
            throw new InvalidArgumentException(
                'O nome do solicitante é inválido.'
            );
        }

        $this->requesterName = $normalizedRequester;
    }

    public function identity(): string
    {
        return hash(
            'sha256',
            implode(
                '|',
                [
                    $this->photoId,
                    $this->profile->value,
                    $this->policyVersion,
                    $this->normalizer,
                    $this->normalizerVersion,
                ]
            )
        );
    }

    private function token(
        string $value,
        string $label,
        int $maximumLength
    ): string {
        $value = trim($value);

        if (
            mb_strlen($value) > $maximumLength
            || preg_match(
                '/\A[a-z0-9][a-z0-9._-]*\z/',
                $value
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                "{$label} é inválida."
            );
        }

        return $value;
    }
}
