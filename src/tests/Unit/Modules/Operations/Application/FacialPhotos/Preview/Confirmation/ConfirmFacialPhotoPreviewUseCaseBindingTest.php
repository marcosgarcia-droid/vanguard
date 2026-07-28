<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Preview\Confirmation;

use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewUseCase;
use Tests\TestCase;

final class ConfirmFacialPhotoPreviewUseCaseBindingTest extends TestCase
{
    public function test_it_is_resolved_by_the_container(): void
    {
        $useCase = app(
            ConfirmFacialPhotoPreviewUseCase::class
        );

        $this->assertInstanceOf(
            ConfirmFacialPhotoPreviewUseCase::class,
            $useCase
        );
    }

    public function test_the_resolution_is_transient(): void
    {
        $first = app(
            ConfirmFacialPhotoPreviewUseCase::class
        );

        $second = app(
            ConfirmFacialPhotoPreviewUseCase::class
        );

        $this->assertNotSame(
            $first,
            $second
        );
    }
}
