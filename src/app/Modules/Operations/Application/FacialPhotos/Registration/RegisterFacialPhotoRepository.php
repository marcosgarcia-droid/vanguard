<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Registration;

interface RegisterFacialPhotoRepository
{
    public function register(
        RegisterFacialPhotoCommand $command
    ): RegisterFacialPhotoResult;
}
