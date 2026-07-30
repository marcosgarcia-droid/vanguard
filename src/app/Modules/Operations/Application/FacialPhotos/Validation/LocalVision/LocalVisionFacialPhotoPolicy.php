<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision;

interface LocalVisionFacialPhotoPolicy
{
    public function decide(
        LocalVisionFacialPhotoAnalysis $analysis
    ): LocalVisionFacialPhotoPolicyResult;
}
