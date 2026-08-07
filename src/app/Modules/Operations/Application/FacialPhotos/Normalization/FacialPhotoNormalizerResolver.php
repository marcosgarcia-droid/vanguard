<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Normalization;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeProfile;

interface FacialPhotoNormalizerResolver
{
    public function resolve(
        FacialPhotoDerivativeProfile $profile
    ): FacialPhotoNormalizer;
}
