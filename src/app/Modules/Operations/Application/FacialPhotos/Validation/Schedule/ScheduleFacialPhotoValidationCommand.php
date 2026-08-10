<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Schedule;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;

final readonly class ScheduleFacialPhotoValidationCommand
{
    public function __construct(
        public string $photoId,
        public FacialPhotoStatus $status,
        public ?int $operatorUserId = null,
    ) {}

    public function awaitsAdditionalValidation(): bool
    {
        return $this->status
            === FacialPhotoStatus::PendingValidation;
    }
}
