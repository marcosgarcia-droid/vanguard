<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Normalization;

interface FacialPhotoNormalizer
{
    public function normalize(
        string $absoluteSourcePath
    ): FacialPhotoNormalizationResult;
}
