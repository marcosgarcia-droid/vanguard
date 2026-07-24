<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis;

use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\FacialPhotoTechnicalAnalyzer;
use App\Modules\Operations\Infrastructure\Images\GdFacialPhotoTechnicalAnalyzer;
use Tests\TestCase;

final class FacialPhotoTechnicalAnalyzerBindingTest extends TestCase
{
    public function test_it_resolves_the_gd_technical_analyzer(): void
    {
        $resolved = app(
            FacialPhotoTechnicalAnalyzer::class
        );

        $this->assertInstanceOf(
            GdFacialPhotoTechnicalAnalyzer::class,
            $resolved
        );
    }
}
