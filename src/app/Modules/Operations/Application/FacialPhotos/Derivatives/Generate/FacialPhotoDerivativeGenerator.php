<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate;

interface FacialPhotoDerivativeGenerator
{
    public function execute(
        GenerateFacialPhotoDerivativeCommand $command
    ): GenerateFacialPhotoDerivativeResult;
}
