<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate;

interface FacialPhotoDerivativeGenerationGuard
{
    public function acquire(
        GenerateFacialPhotoDerivativeCommand $command
    ): FacialPhotoDerivativeGenerationLease;
}
