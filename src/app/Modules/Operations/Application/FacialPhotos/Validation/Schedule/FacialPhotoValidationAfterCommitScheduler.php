<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Schedule;

interface FacialPhotoValidationAfterCommitScheduler
{
    public function schedule(
        ScheduleFacialPhotoValidationCommand $command,
    ): bool;
}
