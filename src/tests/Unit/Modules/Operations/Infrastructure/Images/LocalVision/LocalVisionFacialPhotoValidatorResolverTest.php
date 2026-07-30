<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\LocalVision;

use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorProvider;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolutionException;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorSelection;
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

    public function test_it_resolves_local_vision_when_explicitly_enabled(): void
    {
        $resolver = new ConfiguredFacialPhotoValidatorResolver(
            environment: 'production',
            simulatorEnabled: false,
            localVisionEnabled: true,
        );

        $validator = $resolver->resolve(
            new FacialPhotoValidatorSelection(
                provider: FacialPhotoValidatorProvider::LocalVision,
                scenario: 'simulator-scenario-is-ignored',
            )
        );

        $this->assertInstanceOf(
            LocalVisionFacialPhotoValidator::class,
            $validator
        );
    }
}
