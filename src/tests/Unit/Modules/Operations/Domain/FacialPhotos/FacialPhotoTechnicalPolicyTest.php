<?php

namespace Tests\Unit\Modules\Operations\Domain\FacialPhotos;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalIssue;
use Tests\TestCase;

final class FacialPhotoTechnicalPolicyTest extends TestCase
{
    public function test_it_preserves_documented_intelbras_derivative_limits(): void
    {
        $this->assertSame(
            'image/jpeg',
            config(
                'facial_photos.intelbras_derivative.mime_type'
            )
        );

        $this->assertSame(
            150,
            config(
                'facial_photos.intelbras_derivative.minimum_width'
            )
        );

        $this->assertSame(
            300,
            config(
                'facial_photos.intelbras_derivative.minimum_height'
            )
        );

        $this->assertSame(
            600,
            config(
                'facial_photos.intelbras_derivative.maximum_width'
            )
        );

        $this->assertSame(
            1200,
            config(
                'facial_photos.intelbras_derivative.maximum_height'
            )
        );

        $this->assertSame(
            2.0,
            config(
                'facial_photos.intelbras_derivative.maximum_height_width_ratio'
            )
        );

        $this->assertSame(
            100_000,
            config(
                'facial_photos.intelbras_derivative.maximum_size_bytes'
            )
        );
    }

    public function test_analysis_serializes_safe_feedback(): void
    {
        $analysis = new FacialPhotoTechnicalAnalysis(
            version: 'technical-v1',
            passed: false,
            metrics: [
                'width' => 320,
            ],
            issues: [
                FacialPhotoTechnicalIssue::ResolutionTooLow,
            ],
        );

        $this->assertSame(
            ['resolution_too_low'],
            $analysis->issueCodes()
        );

        $this->assertSame(
            'Resolução insuficiente',
            $analysis->toArray()['issues'][0]['label']
        );

        $this->assertNotEmpty(
            $analysis->toArray()['issues'][0]['guidance']
        );
    }
}
