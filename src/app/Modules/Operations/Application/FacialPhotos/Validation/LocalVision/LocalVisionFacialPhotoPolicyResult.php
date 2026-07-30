<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;

/**
 * Resultado puro da política aplicada sobre as evidências do serviço.
 */
final readonly class LocalVisionFacialPhotoPolicyResult
{
    /**
     * @param  list<FacialPhotoValidationIssue>  $issues
     */
    public function __construct(
        public string $version,
        public FacialPhotoValidationDecision $decision,
        public array $issues,
    ) {}
}
