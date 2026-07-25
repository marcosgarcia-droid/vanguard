<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;

interface FacialPhotoValidator
{
    public function validate(
        string $absolutePath
    ): FacialPhotoValidationResult;
}
