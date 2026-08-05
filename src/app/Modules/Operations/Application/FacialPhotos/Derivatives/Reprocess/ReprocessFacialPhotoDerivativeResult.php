<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;

final readonly class ReprocessFacialPhotoDerivativeResult
{
    public function __construct(
        public string $requestId,
        public string $photoId,
        public ?FacialPhotoDerivativeStatus $previousStatus,
        public bool $scheduled,
    ) {}
}
