<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation\Resolution;

use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolutionException;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolver;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorSelection;
use App\Modules\Operations\Infrastructure\Images\Resolution\ConfiguredFacialPhotoValidatorResolver;
use App\Modules\Operations\Infrastructure\Images\Simulator\SimulatedFacialPhotoValidator;
use Tests\TestCase;

final class FacialPhotoValidatorResolverBindingTest extends TestCase
{
    public function test_it_binds_the_resolver_to_the_configured_implementation(): void
    {
        $this->assertTrue(
            $this->app->bound(
                FacialPhotoValidatorResolver::class
            )
        );

        $this->assertInstanceOf(
            ConfiguredFacialPhotoValidatorResolver::class,
            $this->app->make(
                FacialPhotoValidatorResolver::class
            )
        );
    }

    public function test_it_does_not_bind_a_global_validator(): void
    {
        $this->assertFalse(
            $this->app->bound(
                FacialPhotoValidator::class
            )
        );
    }

    public function test_the_bound_resolver_fails_closed_while_the_simulator_is_disabled(): void
    {
        config()->set(
            'facial_photos.validation.simulator.enabled',
            false
        );

        $resolver = $this->app->make(
            FacialPhotoValidatorResolver::class
        );

        $this->expectException(
            FacialPhotoValidatorResolutionException::class
        );

        $this->expectExceptionMessage(
            'O provider de validação facial informado está desativado.'
        );

        $resolver->resolve(
            FacialPhotoValidatorSelection::fromInput(
                provider: 'simulator',
                scenario: 'approved',
            )
        );
    }

    public function test_each_resolution_reads_the_current_simulator_configuration(): void
    {
        config()->set(
            'facial_photos.validation.simulator.enabled',
            false
        );

        $disabledResolver = $this->app->make(
            FacialPhotoValidatorResolver::class
        );

        config()->set(
            'facial_photos.validation.simulator.enabled',
            true
        );

        $enabledResolver = $this->app->make(
            FacialPhotoValidatorResolver::class
        );

        $this->assertNotSame(
            $disabledResolver,
            $enabledResolver
        );

        $validator = $enabledResolver->resolve(
            FacialPhotoValidatorSelection::fromInput(
                provider: 'simulator',
                scenario: 'approved',
            )
        );

        $this->assertInstanceOf(
            SimulatedFacialPhotoValidator::class,
            $validator
        );

        $this->assertTrue(
            $validator->validate(
                '/tmp/vanguard-bound-facial-validator.jpg'
            )->isApproved()
        );

        $this->assertFalse(
            $this->app->bound(
                FacialPhotoValidator::class
            )
        );
    }
}
