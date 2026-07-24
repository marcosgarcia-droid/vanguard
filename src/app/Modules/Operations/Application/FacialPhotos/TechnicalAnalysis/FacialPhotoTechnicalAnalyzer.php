<?php

namespace App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;

interface FacialPhotoTechnicalAnalyzer
{
    public function analyze(
        string $absolutePath
    ): FacialPhotoTechnicalAnalysis;
}
