<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate;

use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationResult;

interface GenerateFacialPhotoDerivativeRepository
{
    public function prepare(
        GenerateFacialPhotoDerivativeCommand $command
    ): GenerateFacialPhotoDerivativePreparation;

    public function complete(
        GenerateFacialPhotoDerivativePreparation $preparation,
        FacialPhotoNormalizationResult $normalization
    ): GenerateFacialPhotoDerivativeResult;

    public function fail(
        GenerateFacialPhotoDerivativePreparation $preparation,
        string $failureCode
    ): void;
}
