<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation;

use DateTimeImmutable;

final readonly class ConfirmFacialPhotoPreviewCommand
{
    public function __construct(
        public string $encodedReceipt,
        public string $absolutePath,
        public string $expectedStatePath,
        public ?int $userId,
        public DateTimeImmutable $confirmedAt,
    ) {}
}
