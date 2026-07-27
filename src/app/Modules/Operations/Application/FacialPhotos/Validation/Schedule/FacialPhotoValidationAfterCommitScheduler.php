<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Schedule;

use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoResult;

interface FacialPhotoValidationAfterCommitScheduler
{
    public function schedule(
        RegisterVisitorFacialPhotoResult $registration,
        ?int $operatorUserId = null,
    ): bool;
}
