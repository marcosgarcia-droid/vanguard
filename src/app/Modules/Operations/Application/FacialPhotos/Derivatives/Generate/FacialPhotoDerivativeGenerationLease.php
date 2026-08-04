<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate;

interface FacialPhotoDerivativeGenerationLease
{
    public function release(): void;
}
