<?php

namespace App\Modules\Operations\Application\FacialPhotos\Registration;

final readonly class RegisterVisitorFacialPhotoUseCase
{
    public function __construct(
        private RegisterVisitorFacialPhotoRepository $repository,
    ) {}

    public function execute(
        RegisterVisitorFacialPhotoCommand $command
    ): RegisterVisitorFacialPhotoResult {
        return $this->repository->register(
            $command
        );
    }
}
