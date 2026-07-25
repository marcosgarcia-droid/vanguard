<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation;

use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Application\FacialPhotos\Validation\ValidateFacialPhotoUseCase;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use PHPUnit\Framework\TestCase;

final class ValidateFacialPhotoUseCaseTest extends TestCase
{
    public function test_it_delegates_the_absolute_path_to_the_validator(): void
    {
        $expectedResult =
            new FacialPhotoValidationResult(
                validator: 'synthetic-validator',
                version: 'facial-contract-v1',
                decision: FacialPhotoValidationDecision::Inconclusive,
                faceCount: 0,
                metrics: [],
                issues: [
                    FacialPhotoValidationIssue::ValidatorUnavailable,
                ],
            );

        $validator = new class($expectedResult) implements FacialPhotoValidator
        {
            public ?string $receivedPath = null;

            public function __construct(
                private readonly FacialPhotoValidationResult $result,
            ) {}

            public function validate(
                string $absolutePath
            ): FacialPhotoValidationResult {
                $this->receivedPath =
                    $absolutePath;

                return $this->result;
            }
        };

        $useCase =
            new ValidateFacialPhotoUseCase(
                $validator
            );

        $result = $useCase->execute(
            '/tmp/synthetic-facial-photo.jpg'
        );

        $this->assertSame(
            '/tmp/synthetic-facial-photo.jpg',
            $validator->receivedPath
        );

        $this->assertSame(
            $expectedResult,
            $result
        );
    }
}
