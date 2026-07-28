<?php

namespace App\Modules\Operations\Application\FacialPhotos\Registration;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use DateTimeImmutable;

final readonly class RegisterVisitorFacialPhotoCommand
{
    public string $expectedSha256;

    public function __construct(
        public string $visitorId,
        public string $absolutePath,
        public string $originalFileName,
        string $expectedSha256,
        public FacialPhotoSource $source,
        public ?int $createdBy = null,
        public ?DateTimeImmutable $capturedAt = null,
    ) {
        if (
            preg_match(
                '/\A[a-f0-9]{64}\z/',
                $expectedSha256
            ) !== 1
        ) {
            throw RegisterVisitorFacialPhotoException::invalidExpectedFingerprint();
        }

        $this->expectedSha256 =
            $expectedSha256;
    }
}
