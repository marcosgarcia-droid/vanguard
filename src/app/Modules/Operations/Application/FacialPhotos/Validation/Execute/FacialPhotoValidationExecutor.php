<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Execute;

interface FacialPhotoValidationExecutor
{
    public function execute(
        ExecuteFacialPhotoValidationCommand $command
    ): ExecuteFacialPhotoValidationResult;
}
