<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Images\Normalization;

use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationException;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizer;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizerResolver;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeProfile;
use InvalidArgumentException;

final readonly class MappedFacialPhotoNormalizerResolver implements FacialPhotoNormalizerResolver
{
    /**
     * @param  array<string, FacialPhotoNormalizer>  $normalizers
     */
    public function __construct(
        private array $normalizers
    ) {
        if ($this->normalizers === []) {
            throw new InvalidArgumentException(
                'Ao menos um normalizador facial deve ser configurado.'
            );
        }

        foreach (
            $this->normalizers as $profile => $normalizer
        ) {
            FacialPhotoDerivativeProfile::from(
                (string) $profile
            );

            if (! $normalizer instanceof FacialPhotoNormalizer) {
                throw new InvalidArgumentException(
                    'O mapa contém um normalizador facial inválido.'
                );
            }
        }
    }

    public function resolve(
        FacialPhotoDerivativeProfile $profile
    ): FacialPhotoNormalizer {
        $normalizer =
            $this->normalizers[
                $profile->value
            ]
            ?? null;

        if (! $normalizer instanceof FacialPhotoNormalizer) {
            throw FacialPhotoNormalizationException::invalidOutput();
        }

        return $normalizer;
    }
}
