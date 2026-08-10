<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Registration;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSubjectType;
use DateTimeImmutable;

final readonly class RegisterFacialPhotoCommand
{
    public string $expectedSha256;

    public string $confirmationKey;

    public string $confirmationContext;

    public function __construct(
        public FacialPhotoSubjectType $subjectType,
        public string $subjectId,
        public string $absolutePath,
        public string $originalFileName,
        string $expectedSha256,
        public FacialPhotoSource $source,
        string $confirmationKey,
        string $confirmationContext,
        public ?int $createdBy = null,
        public ?DateTimeImmutable $capturedAt = null,
    ) {
        if (
            trim($this->subjectId) === ''
            || trim($this->subjectId) !== $this->subjectId
            || strlen($this->subjectId) > 64
        ) {
            throw RegisterFacialPhotoException::invalidSubject();
        }

        if (
            preg_match(
                '/\A[a-f0-9]{64}\z/',
                $expectedSha256
            ) !== 1
        ) {
            throw RegisterFacialPhotoException::invalidExpectedFingerprint();
        }

        if (
            preg_match(
                '/\A[a-f0-9]{64}\z/',
                $confirmationKey
            ) !== 1
        ) {
            throw RegisterFacialPhotoException::invalidConfirmationProof();
        }

        if (
            trim($confirmationContext) === ''
            || trim($confirmationContext) !== $confirmationContext
            || strlen($confirmationContext) > 255
        ) {
            throw RegisterFacialPhotoException::invalidConfirmationProof();
        }

        $this->expectedSha256 = $expectedSha256;
        $this->confirmationKey = $confirmationKey;
        $this->confirmationContext = $confirmationContext;
    }
}
