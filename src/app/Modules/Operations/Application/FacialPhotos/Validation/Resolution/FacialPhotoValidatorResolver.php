<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Resolution;

use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;

interface FacialPhotoValidatorResolver
{
    public function resolve(
        FacialPhotoValidatorSelection $selection
    ): FacialPhotoValidator;
}
