<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;

final readonly class ReprocessFacialPhotoDerivativeContext
{
    public function __construct(
        public string $photoId,
        public string $requesterName,
        public ?FacialPhotoDerivativeStatus $previousStatus,
    ) {}
}
