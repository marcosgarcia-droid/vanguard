<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\LocalVision;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Infrastructure\Images\LocalVision\LocalVisionFacialPhotoValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LocalVisionFacialPhotoValidatorTest extends TestCase
{
    public function test_it_fails_safely_until_the_vision_service_exists(): void
    {
        $result = (new LocalVisionFacialPhotoValidator)
            ->validate('/tmp/visitor-photo.jpg');

        $this->assertTrue($result->isInconclusive());
        $this->assertSame(0, $result->faceCount);
        $this->assertSame(
            LocalVisionFacialPhotoValidator::VALIDATOR,
            $result->validator
        );
        $this->assertSame(
            LocalVisionFacialPhotoValidator::VERSION,
            $result->version
        );
        $this->assertFalse($result->metrics['available']);
        $this->assertFalse(
            $result->metrics['transport_configured']
        );
        $this->assertTrue(
            $result->hasIssue(
                FacialPhotoValidationIssue::ValidatorUnavailable
            )
        );
    }

    public function test_it_requires_an_absolute_path(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new LocalVisionFacialPhotoValidator)
            ->validate('visitor-photo.jpg');
    }
}
