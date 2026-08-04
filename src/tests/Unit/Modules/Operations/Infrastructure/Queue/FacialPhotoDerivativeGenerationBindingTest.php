<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Queue;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\FacialPhotoDerivativeGenerationGuard;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\FacialPhotoDerivativeGenerator;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeRepository;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeUseCase;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Schedule\FacialPhotoDerivativeAfterCommitScheduler;
use App\Modules\Operations\Infrastructure\Concurrency\CacheFacialPhotoDerivativeGenerationGuard;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentGenerateFacialPhotoDerivativeRepository;
use App\Modules\Operations\Infrastructure\Queue\LaravelFacialPhotoDerivativeAfterCommitScheduler;
use ReflectionProperty;
use Tests\TestCase;

final class FacialPhotoDerivativeGenerationBindingTest extends TestCase
{
    public function test_it_resolves_the_generation_services(): void
    {
        $this->assertInstanceOf(
            EloquentGenerateFacialPhotoDerivativeRepository::class,
            app(
                GenerateFacialPhotoDerivativeRepository::class
            )
        );

        $this->assertInstanceOf(
            CacheFacialPhotoDerivativeGenerationGuard::class,
            app(
                FacialPhotoDerivativeGenerationGuard::class
            )
        );

        $this->assertInstanceOf(
            GenerateFacialPhotoDerivativeUseCase::class,
            app(
                FacialPhotoDerivativeGenerator::class
            )
        );

        $this->assertInstanceOf(
            LaravelFacialPhotoDerivativeAfterCommitScheduler::class,
            app(
                FacialPhotoDerivativeAfterCommitScheduler::class
            )
        );
    }

    public function test_the_scheduler_is_transient_and_fail_closed(): void
    {
        config()->set(
            'facial_photos.normalization.async_generation.enabled',
            false
        );

        $first = app(
            FacialPhotoDerivativeAfterCommitScheduler::class
        );

        config()->set(
            'facial_photos.normalization.async_generation.enabled',
            true
        );

        $second = app(
            FacialPhotoDerivativeAfterCommitScheduler::class
        );

        $this->assertNotSame(
            $first,
            $second
        );

        $this->assertFalse(
            $this->property(
                $first,
                'enabled'
            )
        );

        $this->assertTrue(
            $this->property(
                $second,
                'enabled'
            )
        );
    }

    private function property(
        object $object,
        string $name
    ): mixed {
        return (
            new ReflectionProperty(
                $object,
                $name
            )
        )->getValue(
            $object
        );
    }
}
