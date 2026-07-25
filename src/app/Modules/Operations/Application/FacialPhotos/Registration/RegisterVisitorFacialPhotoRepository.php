<?php

namespace App\Modules\Operations\Application\FacialPhotos\Registration;

interface RegisterVisitorFacialPhotoRepository
{
    public function register(
        RegisterVisitorFacialPhotoCommand $command
    ): RegisterVisitorFacialPhotoResult;
}
