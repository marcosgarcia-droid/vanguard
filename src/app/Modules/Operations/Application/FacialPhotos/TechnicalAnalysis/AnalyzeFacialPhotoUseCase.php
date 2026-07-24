<?php

namespace App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;

final readonly class AnalyzeFacialPhotoUseCase
{
    public function __construct(
        private FacialPhotoTechnicalAnalyzer $analyzer,
    ) {}

    public function execute(
        string $absolutePath
    ): FacialPhotoTechnicalAnalysis {
        return $this->analyzer->analyze(
            $absolutePath
        );
    }
}
