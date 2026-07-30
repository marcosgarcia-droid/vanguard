<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\LocalVision;

use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoAnalysis;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClient;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClientException;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClientFailure;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoPolicy;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Infrastructure\Images\LocalVision\IntelbrasLocalVisionFacialPhotoPolicy;
use App\Modules\Operations\Infrastructure\Images\LocalVision\LocalVisionFacialPhotoValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LocalVisionFacialPhotoValidatorTest extends TestCase
{
    public function test_it_fails_safely_without_an_injected_client(): void
    {
        $result = (new LocalVisionFacialPhotoValidator)
            ->validate('/tmp/visitor-photo.jpg');

        $this->assertTrue($result->isInconclusive());
        $this->assertSame(0, $result->faceCount);
        $this->assertFalse($result->metrics['available']);
        $this->assertFalse(
            $result->metrics['transport_configured']
        );
        $this->assertSame(
            LocalVisionFacialPhotoClientFailure::InvalidConfiguration->value,
            $result->metrics['failure']
        );
        $this->assertTrue(
            $result->hasIssue(
                FacialPhotoValidationIssue::ValidatorUnavailable
            )
        );
    }

    public function test_it_keeps_valid_evidence_inconclusive_without_a_policy(): void
    {
        $client = $this->clientReturning(
            $this->validAnalysis()
        );

        $result = (new LocalVisionFacialPhotoValidator($client))
            ->validate('/tmp/visitor-photo.jpg');

        $this->assertTrue($result->isInconclusive());
        $this->assertFalse($result->isApproved());
        $this->assertFalse($result->isRejected());
        $this->assertFalse(
            $result->metrics['policy_configured']
        );
        $this->assertTrue(
            $result->hasIssue(
                FacialPhotoValidationIssue::ValidationPolicyUnavailable
            )
        );
    }

    public function test_it_applies_the_injected_policy(): void
    {
        $analysis = $this->validAnalysis(
            [
                'face_ratio' => 0.20,
                'centered' => true,
                'frontal' => true,
                'eyes_open' => true,
                'occluded' => false,
            ]
        );

        $validator = new LocalVisionFacialPhotoValidator(
            client: $this->clientReturning($analysis),
            policy: new IntelbrasLocalVisionFacialPhotoPolicy(
                minimumFaceRatio: 1 / 3,
                maximumFaceRatio: 2 / 3,
            ),
        );

        $result = $validator->validate(
            '/tmp/visitor-photo.jpg'
        );

        $this->assertTrue($result->isRejected());
        $this->assertTrue(
            $result->metrics['policy_configured']
        );
        $this->assertFalse(
            $result->metrics['approval_calibrated']
        );
        $this->assertTrue(
            $result->hasIssue(
                FacialPhotoValidationIssue::FaceTooSmall
            )
        );
    }

    public function test_it_contains_unexpected_policy_failures(): void
    {
        $policy = $this->createMock(
            LocalVisionFacialPhotoPolicy::class
        );

        $policy
            ->method('decide')
            ->willThrowException(
                new RuntimeException(
                    'Synthetic policy failure.'
                )
            );

        $validator = new LocalVisionFacialPhotoValidator(
            client: $this->clientReturning(
                $this->validAnalysis()
            ),
            policy: $policy,
        );

        $result = $validator->validate(
            '/tmp/visitor-photo.jpg'
        );

        $this->assertTrue($result->isInconclusive());
        $this->assertSame(
            'invalid_policy_result',
            $result->metrics['failure']
        );
        $this->assertTrue(
            $result->hasIssue(
                FacialPhotoValidationIssue::InvalidValidatorResponse
            )
        );
    }

    public function test_it_maps_service_unavailability_to_an_inconclusive_result(): void
    {
        $client = $this->createMock(
            LocalVisionFacialPhotoClient::class
        );

        $client
            ->method('analyze')
            ->willThrowException(
                LocalVisionFacialPhotoClientException::serviceUnavailable()
            );

        $result = (new LocalVisionFacialPhotoValidator($client))
            ->validate('/tmp/visitor-photo.jpg');

        $this->assertTrue($result->isInconclusive());
        $this->assertFalse($result->metrics['available']);
        $this->assertTrue(
            $result->metrics['transport_configured']
        );
        $this->assertSame(
            LocalVisionFacialPhotoClientFailure::ServiceUnavailable->value,
            $result->metrics['failure']
        );
    }

    public function test_it_maps_rejected_requests_to_invalid_validator_responses(): void
    {
        $client = $this->createMock(
            LocalVisionFacialPhotoClient::class
        );

        $client
            ->method('analyze')
            ->willThrowException(
                LocalVisionFacialPhotoClientException::requestRejected(422)
            );

        $result = (new LocalVisionFacialPhotoValidator($client))
            ->validate('/tmp/visitor-photo.jpg');

        $this->assertTrue($result->isInconclusive());
        $this->assertTrue($result->metrics['available']);
        $this->assertTrue(
            $result->hasIssue(
                FacialPhotoValidationIssue::InvalidValidatorResponse
            )
        );
    }

    public function test_it_contains_unexpected_client_failures(): void
    {
        $client = $this->createMock(
            LocalVisionFacialPhotoClient::class
        );

        $client
            ->method('analyze')
            ->willThrowException(
                new RuntimeException(
                    'Synthetic unexpected failure.'
                )
            );

        $result = (new LocalVisionFacialPhotoValidator($client))
            ->validate('/tmp/visitor-photo.jpg');

        $this->assertTrue($result->isInconclusive());
        $this->assertSame(
            'unexpected_failure',
            $result->metrics['failure']
        );
    }

    public function test_it_requires_an_absolute_path(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new LocalVisionFacialPhotoValidator)
            ->validate('visitor-photo.jpg');
    }

    private function clientReturning(
        LocalVisionFacialPhotoAnalysis $analysis
    ): LocalVisionFacialPhotoClient {
        $client = $this->createMock(
            LocalVisionFacialPhotoClient::class
        );

        $client
            ->method('analyze')
            ->willReturn($analysis);

        return $client;
    }

    /**
     * @param  array<string, bool|int|float|string|null>|null  $metrics
     */
    private function validAnalysis(
        ?array $metrics = null
    ): LocalVisionFacialPhotoAnalysis {
        return new LocalVisionFacialPhotoAnalysis(
            serviceVersion: '0.1.0',
            engine: 'mediapipe-opencv',
            engineVersion: 'foundation',
            faceCount: 1,
            metrics: $metrics ?? [
                'face_ratio' => 0.50,
                'centered' => true,
                'frontal' => true,
                'eyes_open' => true,
                'occluded' => false,
            ],
        );
    }
}
