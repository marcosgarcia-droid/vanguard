<?php

namespace App\Modules\Operations\Application\FacialPhotos\Registration;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use DateTimeImmutable;

final readonly class RegisterVisitorFacialPhotoCommand
{
    public function __construct(
        public string $visitorId,
        public string $absolutePath,
        public string $originalFileName,
        public FacialPhotoSource $source,
        public ?int $createdBy = null,
        public ?DateTimeImmutable $capturedAt = null,
    ) {}
}
