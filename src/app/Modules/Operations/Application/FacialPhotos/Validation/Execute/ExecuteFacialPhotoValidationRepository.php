<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation\Execute;

interface ExecuteFacialPhotoValidationRepository
{
    public function findTarget(
        string $photoId
    ): ?FacialPhotoValidationTarget;

    public function persist(
        FacialPhotoValidationPersistenceData $data
    ): ExecuteFacialPhotoValidationResult;
}
