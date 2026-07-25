<?php

namespace App\Modules\Operations\Application\FacialPhotos\Validation;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;

final readonly class ValidateFacialPhotoUseCase
{
    public function __construct(
        private FacialPhotoValidator $validator,
    ) {}

    public function execute(
        string $absolutePath
    ): FacialPhotoValidationResult {
        return $this->validator->validate(
            $absolutePath
        );
    }
}
