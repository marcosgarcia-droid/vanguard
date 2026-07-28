<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Preview;

use App\Modules\Operations\Application\FacialPhotos\Preview\PreviewFacialPhotoUseCase;
use ReflectionProperty;
use Tests\TestCase;

final class PreviewFacialPhotoUseCaseBindingTest extends TestCase
{
    public function test_it_resolves_the_preview_use_case(): void
    {
        $this->assertInstanceOf(
            PreviewFacialPhotoUseCase::class,
            app(
                PreviewFacialPhotoUseCase::class
            )
        );
    }

    public function test_the_binding_is_transient(): void
    {
        $first = app(
            PreviewFacialPhotoUseCase::class
        );

        $second = app(
            PreviewFacialPhotoUseCase::class
        );

        $this->assertNotSame(
            $first,
            $second
        );
    }

    public function test_each_resolution_reads_the_current_configuration(): void
    {
        config()->set(
            'facial_photos.validation.enabled',
            false
        );

        config()->set(
            'facial_photos.validation.provider',
            null
        );

        config()->set(
            'facial_photos.validation.simulator.default_scenario',
            'validator_unavailable'
        );

        $disabled = app(
            PreviewFacialPhotoUseCase::class
        );

        $this->assertFalse(
            self::property(
                $disabled,
                'facialValidationEnabled'
            )
        );

        $this->assertNull(
            self::property(
                $disabled,
                'provider'
            )
        );

        config()->set(
            'facial_photos.validation.enabled',
            true
        );

        config()->set(
            'facial_photos.validation.provider',
            'simulator'
        );

        config()->set(
            'facial_photos.validation.simulator.default_scenario',
            'approved'
        );

        $enabled = app(
            PreviewFacialPhotoUseCase::class
        );

        $this->assertTrue(
            self::property(
                $enabled,
                'facialValidationEnabled'
            )
        );

        $this->assertSame(
            'simulator',
            self::property(
                $enabled,
                'provider'
            )
        );

        $this->assertSame(
            'approved',
            self::property(
                $enabled,
                'scenario'
            )
        );
    }

    private static function property(
        PreviewFacialPhotoUseCase $useCase,
        string $name
    ): mixed {
        return (
            new ReflectionProperty(
                $useCase,
                $name
            )
        )->getValue(
            $useCase
        );
    }
}
