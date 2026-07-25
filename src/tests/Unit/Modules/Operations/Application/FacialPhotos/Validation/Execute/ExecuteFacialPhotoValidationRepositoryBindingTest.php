<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationRepository;
use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentExecuteFacialPhotoValidationRepository;
use Tests\TestCase;

final class ExecuteFacialPhotoValidationRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_eloquent_repository(): void
    {
        $repository = app(
            ExecuteFacialPhotoValidationRepository::class
        );

        $this->assertInstanceOf(
            EloquentExecuteFacialPhotoValidationRepository::class,
            $repository
        );
    }

    public function test_it_keeps_the_facial_validator_unbound_globally(): void
    {
        $this->assertFalse(
            app()->bound(
                FacialPhotoValidator::class
            )
        );
    }
}
