<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation\Schedule;

use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationExecutor;
use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Application\FacialPhotos\Validation\Schedule\FacialPhotoValidationAfterCommitScheduler;
use App\Modules\Operations\Infrastructure\Validation\LaravelFacialPhotoValidationAfterCommitScheduler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Connection;
use ReflectionProperty;
use Tests\TestCase;

final class FacialPhotoValidationAfterCommitSchedulerBindingTest extends TestCase
{
    public function test_it_binds_the_scheduler_to_the_laravel_implementation(): void
    {
        $this->assertTrue(
            $this->app->bound(
                FacialPhotoValidationAfterCommitScheduler::class
            )
        );

        $scheduler = $this->app->make(
            FacialPhotoValidationAfterCommitScheduler::class
        );

        $this->assertInstanceOf(
            LaravelFacialPhotoValidationAfterCommitScheduler::class,
            $scheduler
        );

        $this->assertTrue(
            $this->app->bound(
                FacialPhotoValidationExecutor::class
            )
        );

        $this->assertFalse(
            $this->app->bound(
                FacialPhotoValidator::class
            )
        );
    }

    public function test_the_scheduler_binding_is_transient(): void
    {
        $first = $this->app->make(
            FacialPhotoValidationAfterCommitScheduler::class
        );

        $second = $this->app->make(
            FacialPhotoValidationAfterCommitScheduler::class
        );

        $this->assertInstanceOf(
            LaravelFacialPhotoValidationAfterCommitScheduler::class,
            $first
        );

        $this->assertInstanceOf(
            LaravelFacialPhotoValidationAfterCommitScheduler::class,
            $second
        );

        $this->assertNotSame(
            $first,
            $second
        );
    }

    public function test_each_resolution_reads_the_current_feature_flag(): void
    {
        config()->set(
            'facial_photos.validation.enabled',
            false
        );

        $disabled = $this->app->make(
            FacialPhotoValidationAfterCommitScheduler::class
        );

        config()->set(
            'facial_photos.validation.enabled',
            true
        );

        $enabled = $this->app->make(
            FacialPhotoValidationAfterCommitScheduler::class
        );

        $this->assertFalse(
            $this->property(
                $disabled,
                'enabled'
            )
        );

        $this->assertTrue(
            $this->property(
                $enabled,
                'enabled'
            )
        );

        $this->assertNotSame(
            $disabled,
            $enabled
        );
    }

    public function test_it_resolves_only_the_required_dependencies(): void
    {
        $scheduler = $this->app->make(
            FacialPhotoValidationAfterCommitScheduler::class
        );

        $this->assertInstanceOf(
            FacialPhotoValidationExecutor::class,
            $this->property(
                $scheduler,
                'executor'
            )
        );

        $this->assertInstanceOf(
            Connection::class,
            $this->property(
                $scheduler,
                'connection'
            )
        );

        $this->assertInstanceOf(
            ExceptionHandler::class,
            $this->property(
                $scheduler,
                'exceptionHandler'
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
