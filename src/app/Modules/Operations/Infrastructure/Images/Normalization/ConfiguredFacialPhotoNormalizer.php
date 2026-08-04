<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Images\Normalization;

use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationException;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationResult;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizer;

final readonly class ConfiguredFacialPhotoNormalizer implements FacialPhotoNormalizer
{
    public function __construct(
        private bool $enabled,
        private FacialPhotoNormalizer $normalizer
    ) {}

    public function normalize(
        string $absoluteSourcePath
    ): FacialPhotoNormalizationResult {
        if (! $this->enabled) {
            throw FacialPhotoNormalizationException::disabled();
        }

        return $this->normalizer->normalize(
            $absoluteSourcePath
        );
    }
}
