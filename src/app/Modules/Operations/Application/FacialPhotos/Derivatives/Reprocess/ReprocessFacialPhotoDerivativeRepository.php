<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess;

interface ReprocessFacialPhotoDerivativeRepository
{
    public function prepare(
        ReprocessFacialPhotoDerivativeCommand $command,
        string $profile,
        string $policyVersion,
    ): ReprocessFacialPhotoDerivativeContext;
}
