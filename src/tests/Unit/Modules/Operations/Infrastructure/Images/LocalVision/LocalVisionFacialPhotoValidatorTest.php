<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\LocalVision;

use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoAnalysis;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClient;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClientException;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClientFailure;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
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

    public function test_it_keeps_valid_evidence_inconclusive_until_the_policy_exists(): void
    {
        $client = $this->createMock(
            LocalVisionFacialPhotoClient::class
        );

        $client
            ->expects($this->once())
            ->method('analyze')
            ->with('/tmp/visitor-photo.jpg')
            ->willReturn(
                new LocalVisionFacialPhotoAnalysis(
                    serviceVersion: '0.1.0',
                    engine: 'mediapipe-opencv',
                    engineVersion: 'foundation',
                    faceCount: 1,
                    metrics: [
                        'detection_confidence' => 0.98,
                        'face_ratio' => 0.47,
                        'centered' => true,
                    ],
                )
            );

        $result = (new LocalVisionFacialPhotoValidator($client))
            ->validate('/tmp/visitor-photo.jpg');

        $this->assertTrue($result->isInconclusive());
        $this->assertFalse($result->isApproved());
        $this->assertFalse($result->isRejected());
        $this->assertSame(1, $result->faceCount);
        $this->assertTrue($result->metrics['available']);
        $this->assertTrue(
            $result->metrics['transport_configured']
        );
        $this->assertFalse(
            $result->metrics['policy_configured']
        );
        $this->assertSame(
            'mediapipe-opencv',
            $result->metrics['engine']
        );
        $this->assertSame(
            0.98,
            $result->metrics['detection_confidence']
        );
        $this->assertTrue(
            $result->hasIssue(
                FacialPhotoValidationIssue::ValidationPolicyUnavailable
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
        $this->assertTrue(
            $result->hasIssue(
                FacialPhotoValidationIssue::ValidatorUnavailable
            )
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
        $this->assertSame(
            LocalVisionFacialPhotoClientFailure::RequestRejected->value,
            $result->metrics['failure']
        );
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
        $this->assertTrue(
            $result->hasIssue(
                FacialPhotoValidationIssue::InvalidValidatorResponse
            )
        );
    }

    public function test_it_requires_an_absolute_path(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new LocalVisionFacialPhotoValidator)
            ->validate('visitor-photo.jpg');
    }
}
