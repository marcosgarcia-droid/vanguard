<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Schedule;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;

interface FacialPhotoDerivativeAfterCommitScheduler
{
    public function schedule(
        GenerateFacialPhotoDerivativeCommand $command
    ): bool;
}
