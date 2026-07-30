<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision;

interface LocalVisionFacialPhotoClient
{
    public function analyze(
        string $absolutePath
    ): LocalVisionFacialPhotoAnalysis;
}
