<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ConfiguredFacialPhotoValidationExecutor;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationRepository;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationExecutor;
use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolver;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransitionPolicy;
use ReflectionProperty;
use Tests\TestCase;

final class FacialPhotoValidationExecutorBindingTest extends TestCase
{
    public function test_it_binds_the_executor_to_the_configured_implementation(): void
    {
        $this->assertTrue(
            $this->app->bound(
                FacialPhotoValidationExecutor::class
            )
        );

        $this->assertInstanceOf(
            ConfiguredFacialPhotoValidationExecutor::class,
            $this->app->make(
                FacialPhotoValidationExecutor::class
            )
        );

        $this->assertFalse(
            $this->app->bound(
                FacialPhotoValidator::class
            )
        );
    }

    public function test_the_executor_binding_is_transient(): void
    {
        $first = $this->app->make(
            FacialPhotoValidationExecutor::class
        );

        $second = $this->app->make(
            FacialPhotoValidationExecutor::class
        );

        $this->assertNotSame(
            $first,
            $second
        );

        $this->assertInstanceOf(
            ConfiguredFacialPhotoValidationExecutor::class,
            $first
        );

        $this->assertInstanceOf(
            ConfiguredFacialPhotoValidationExecutor::class,
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

        $disabled = $this->app->make(
            FacialPhotoValidationExecutor::class
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

        $enabled = $this->app->make(
            FacialPhotoValidationExecutor::class
        );

        $this->assertFalse(
            $this->property(
                $disabled,
                'enabled'
            )
        );

        $this->assertNull(
            $this->property(
                $disabled,
                'provider'
            )
        );

        $this->assertSame(
            'validator_unavailable',
            $this->property(
                $disabled,
                'scenario'
            )
        );

        $this->assertTrue(
            $this->property(
                $enabled,
                'enabled'
            )
        );

        $this->assertSame(
            'simulator',
            $this->property(
                $enabled,
                'provider'
            )
        );

        $this->assertSame(
            'approved',
            $this->property(
                $enabled,
                'scenario'
            )
        );

        $this->assertNotSame(
            $disabled,
            $enabled
        );
    }

    public function test_it_resolves_only_the_required_dependencies(): void
    {
        $executor = $this->app->make(
            FacialPhotoValidationExecutor::class
        );

        $this->assertInstanceOf(
            FacialPhotoValidatorResolver::class,
            $this->property(
                $executor,
                'resolver'
            )
        );

        $this->assertInstanceOf(
            ExecuteFacialPhotoValidationRepository::class,
            $this->property(
                $executor,
                'repository'
            )
        );

        $this->assertInstanceOf(
            FacialPhotoStatusTransitionPolicy::class,
            $this->property(
                $executor,
                'transitionPolicy'
            )
        );

        $this->assertFalse(
            $this->app->bound(
                FacialPhotoValidator::class
            )
        );
    }

    private function property(
        object $object,
        string $property
    ): mixed {
        return (
            new ReflectionProperty(
                $object,
                $property
            )
        )->getValue(
            $object
        );
    }
}
