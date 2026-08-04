<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\LocalVision;

use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoAnalysis;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Infrastructure\Images\LocalVision\IntelbrasLocalVisionFacialPhotoPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class IntelbrasLocalVisionFacialPhotoPolicyTest extends TestCase
{
    public function test_it_rejects_when_no_face_is_detected(): void
    {
        $result = $this->policy()->decide(
            $this->analysis(
                faceCount: 0,
                metrics: []
            )
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Rejected,
            $result->decision
        );

        $this->assertSame(
            [FacialPhotoValidationIssue::NoFaceDetected],
            $result->issues
        );
    }

    public function test_it_rejects_when_multiple_faces_are_detected(): void
    {
        $result = $this->policy()->decide(
            $this->analysis(
                faceCount: 2,
                metrics: []
            )
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Rejected,
            $result->decision
        );

        $this->assertSame(
            [FacialPhotoValidationIssue::MultipleFacesDetected],
            $result->issues
        );
    }

    public function test_it_keeps_missing_required_evidence_inconclusive(): void
    {
        $metrics = $this->validMetrics();

        unset($metrics['eyes_open']);

        $result = $this->policy()->decide(
            $this->analysis(
                metrics: $metrics
            )
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Inconclusive,
            $result->decision
        );

        $this->assertSame(
            [FacialPhotoValidationIssue::InvalidValidatorResponse],
            $result->issues
        );
    }

    public function test_it_rejects_an_invalid_optional_occlusion_type(): void
    {
        $metrics = $this->validMetrics();
        $metrics['occluded'] = 'unknown';

        $result = $this->policy()->decide(
            $this->analysis(
                metrics: $metrics
            )
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Inconclusive,
            $result->decision
        );

        $this->assertSame(
            [FacialPhotoValidationIssue::InvalidValidatorResponse],
            $result->issues
        );
    }

    public function test_it_rejects_all_deterministic_violations(): void
    {
        $result = $this->policy()->decide(
            $this->analysis(
                metrics: [
                    'face_ratio' => 0.20,
                    'centered' => false,
                    'frontal' => false,
                    'eyes_open' => false,
                    'occluded' => true,
                ]
            )
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Rejected,
            $result->decision
        );

        $this->assertSame(
            [
                FacialPhotoValidationIssue::FaceTooSmall,
                FacialPhotoValidationIssue::FaceNotCentered,
                FacialPhotoValidationIssue::HeadPoseInvalid,
                FacialPhotoValidationIssue::EyesNotVisible,
                FacialPhotoValidationIssue::FaceOccluded,
            ],
            $result->issues
        );
    }

    public function test_it_rejects_a_face_above_the_allowed_ratio(): void
    {
        $metrics = $this->validMetrics();
        $metrics['face_ratio'] = 0.80;

        $result = $this->policy()->decide(
            $this->analysis(
                metrics: $metrics
            )
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Rejected,
            $result->decision
        );

        $this->assertSame(
            [FacialPhotoValidationIssue::FaceTooLarge],
            $result->issues
        );
    }

    public function test_it_approves_when_supported_local_requirements_pass(): void
    {
        $result = $this->policy()->decide(
            $this->analysis()
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Approved,
            $result->decision
        );

        $this->assertSame(
            [],
            $result->issues
        );

        $this->assertSame(
            'vanguard-local-quality-v1',
            $result->version
        );
    }

    public function test_it_approves_when_occlusion_evidence_is_absent(): void
    {
        $metrics = $this->validMetrics();

        unset($metrics['occluded']);

        $result = $this->policy()->decide(
            $this->analysis(
                metrics: $metrics
            )
        );

        $this->assertSame(
            FacialPhotoValidationDecision::Approved,
            $result->decision
        );

        $this->assertSame([], $result->issues);
    }

    public function test_it_rejects_invalid_policy_limits(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasLocalVisionFacialPhotoPolicy(
            minimumFaceRatio: 0.80,
            maximumFaceRatio: 0.20,
        );
    }

    private function policy(): IntelbrasLocalVisionFacialPhotoPolicy
    {
        return new IntelbrasLocalVisionFacialPhotoPolicy(
            minimumFaceRatio: 1 / 3,
            maximumFaceRatio: 2 / 3,
        );
    }

    /**
     * @param  array<string, bool|int|float|string|null>|null  $metrics
     */
    private function analysis(
        int $faceCount = 1,
        ?array $metrics = null,
    ): LocalVisionFacialPhotoAnalysis {
        return new LocalVisionFacialPhotoAnalysis(
            serviceVersion: 'foundation-v1',
            engine: 'mediapipe-opencv',
            engineVersion: 'foundation',
            faceCount: $faceCount,
            metrics: $metrics ?? $this->validMetrics(),
        );
    }

    /**
     * @return array<string, bool|int|float|string|null>
     */
    private function validMetrics(): array
    {
        return [
            'face_ratio' => 0.50,
            'centered' => true,
            'frontal' => true,
            'eyes_open' => true,
            'occluded' => null,
        ];
    }
}
