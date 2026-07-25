<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Registration;

use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentRegisterVisitorFacialPhotoRepository;
use Tests\TestCase;

final class RegisterVisitorFacialPhotoRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_eloquent_repository(): void
    {
        $repository = app(
            RegisterVisitorFacialPhotoRepository::class
        );

        $this->assertInstanceOf(
            EloquentRegisterVisitorFacialPhotoRepository::class,
            $repository
        );
    }
}
