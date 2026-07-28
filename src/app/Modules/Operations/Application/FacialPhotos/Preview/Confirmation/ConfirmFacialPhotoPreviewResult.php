<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoPreviewDecision;

final readonly class ConfirmFacialPhotoPreviewResult
{
    public function __construct(
        public string $fingerprint,
        public FacialPhotoPreviewDecision $decision,
    ) {}

    public function awaitsAdditionalValidation(): bool
    {
        return $this->decision
            === FacialPhotoPreviewDecision::Inconclusive;
    }
}
