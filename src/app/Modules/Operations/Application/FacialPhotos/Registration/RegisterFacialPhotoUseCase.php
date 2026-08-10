<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Registration;

final readonly class RegisterFacialPhotoUseCase
{
    public function __construct(
        private RegisterFacialPhotoRepository $repository,
    ) {}

    public function execute(
        RegisterFacialPhotoCommand $command
    ): RegisterFacialPhotoResult {
        return $this->repository->register(
            $command
        );
    }
}
