<?php

namespace App\Modules\Operations\Application\FacialPhotos\Registration;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;

final readonly class RegisterVisitorFacialPhotoResult
{
    public function __construct(
        public string $photoId,
        public FacialPhotoStatus $status,
        public FacialPhotoTechnicalAnalysis $technicalAnalysis,
    ) {}

    public function awaitsAdditionalValidation(): bool
    {
        return $this->status
            === FacialPhotoStatus::PendingValidation;
    }

    public function isRejected(): bool
    {
        return $this->status
            === FacialPhotoStatus::Rejected;
    }
}
