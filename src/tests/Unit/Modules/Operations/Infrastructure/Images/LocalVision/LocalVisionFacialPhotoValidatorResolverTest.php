<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\LocalVision;

use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoAnalysis;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClient;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorProvider;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolutionException;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorSelection;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Infrastructure\Images\LocalVision\LocalVisionFacialPhotoValidator;
use App\Modules\Operations\Infrastructure\Images\Resolution\ConfiguredFacialPhotoValidatorResolver;
use PHPUnit\Framework\TestCase;

final class LocalVisionFacialPhotoValidatorResolverTest extends TestCase
{
    public function test_it_normalizes_the_local_vision_provider(): void
    {
        $this->assertSame(
            FacialPhotoValidatorProvider::LocalVision,
            FacialPhotoValidatorProvider::fromInput(
                ' LOCAL_VISION '
            )
        );
    }

    public function test_it_blocks_local_vision_while_disabled(): void
    {
        $resolver = new ConfiguredFacialPhotoValidatorResolver(
            environment: 'local',
            simulatorEnabled: false,
            localVisionEnabled: false,
        );

        $this->expectException(
            FacialPhotoValidatorResolutionException::class
        );

        $resolver->resolve(
            new FacialPhotoValidatorSelection(
                provider: FacialPhotoValidatorProvider::LocalVision,
            )
        );
    }

    public function test_it_injects_the_configured_client_into_local_vision(): void
    {
        $client = $this->createMock(
            LocalVisionFacialPhotoClient::class
        );

        $client
            ->expects($this->once())
            ->method('analyze')
            ->willReturn(
                new LocalVisionFacialPhotoAnalysis(
                    serviceVersion: '0.1.0',
                    engine: 'mediapipe-opencv',
                    engineVersion: 'foundation',
                    faceCount: 1,
                    metrics: [
                        'centered' => true,
                    ],
                )
            );

        $resolver = new ConfiguredFacialPhotoValidatorResolver(
            environment: 'production',
            simulatorEnabled: false,
            localVisionEnabled: true,
            localVisionValidator: new LocalVisionFacialPhotoValidator(
                client: $client
            ),
        );

        $validator = $resolver->resolve(
            new FacialPhotoValidatorSelection(
                provider: FacialPhotoValidatorProvider::LocalVision,
            )
        );

        $this->assertInstanceOf(
            LocalVisionFacialPhotoValidator::class,
            $validator
        );

        $result = $validator->validate(
            '/tmp/visitor-photo.jpg'
        );

        $this->assertTrue($result->isInconclusive());
        $this->assertTrue(
            $result->hasIssue(
                FacialPhotoValidationIssue::ValidationPolicyUnavailable
            )
        );
    }
}
