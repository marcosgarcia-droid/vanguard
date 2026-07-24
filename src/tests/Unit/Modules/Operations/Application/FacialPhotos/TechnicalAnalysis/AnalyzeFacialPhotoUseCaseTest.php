<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis;

use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\AnalyzeFacialPhotoUseCase;
use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\FacialPhotoTechnicalAnalyzer;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;
use PHPUnit\Framework\TestCase;

final class AnalyzeFacialPhotoUseCaseTest extends TestCase
{
    public function test_it_delegates_the_absolute_path_to_the_analyzer(): void
    {
        $analyzer = new class implements FacialPhotoTechnicalAnalyzer
        {
            public ?string $receivedPath = null;

            public function analyze(
                string $absolutePath
            ): FacialPhotoTechnicalAnalysis {
                $this->receivedPath = $absolutePath;

                return new FacialPhotoTechnicalAnalysis(
                    version: 'fake-v1',
                    passed: true,
                    metrics: [
                        'width' => 720,
                        'height' => 900,
                    ],
                    issues: [],
                );
            }
        };

        $useCase = new AnalyzeFacialPhotoUseCase(
            $analyzer
        );

        $result = $useCase->execute(
            '/tmp/visitor-photo.jpg'
        );

        $this->assertSame(
            '/tmp/visitor-photo.jpg',
            $analyzer->receivedPath
        );

        $this->assertTrue(
            $result->passed
        );

        $this->assertSame(
            'fake-v1',
            $result->version
        );
    }
}
