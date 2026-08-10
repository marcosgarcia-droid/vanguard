<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Registration;

use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoCommand;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoRepository;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoResult;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoUseCase;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSubjectType;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;
use Tests\TestCase;

final class RegisterFacialPhotoUseCaseTest extends TestCase
{
    public function test_it_delegates_generic_registration_to_repository(): void
    {
        $analysis = new FacialPhotoTechnicalAnalysis(
            passed: true,
            metrics: [],
            issues: [],
            version: 'test',
        );

        $result = new RegisterFacialPhotoResult(
            photoId: 'photo-generic-1',
            status: FacialPhotoStatus::PendingValidation,
            technicalAnalysis: $analysis,
        );

        $repository = new class($result) implements RegisterFacialPhotoRepository
        {
            public ?RegisterFacialPhotoCommand $receivedCommand = null;

            public function __construct(
                private readonly RegisterFacialPhotoResult $result,
            ) {}

            public function register(
                RegisterFacialPhotoCommand $command
            ): RegisterFacialPhotoResult {
                $this->receivedCommand = $command;

                return $this->result;
            }
        };

        $useCase = new RegisterFacialPhotoUseCase(
            $repository
        );

        $command = new RegisterFacialPhotoCommand(
            subjectType: FacialPhotoSubjectType::Employee,
            subjectId: '11111111-1111-4111-8111-111111111111',
            absolutePath: '/tmp/employee-face.jpg',
            originalFileName: 'employee-face.jpg',
            expectedSha256: str_repeat('a', 64),
            source: FacialPhotoSource::Webcam,
            confirmationKey: str_repeat('b', 64),
            confirmationContext: 'employee.update.'
                .'11111111-1111-4111-8111-111111111111'
                .'.photo_capture',
        );

        $actual = $useCase->execute(
            $command
        );

        self::assertSame(
            $result,
            $actual
        );

        self::assertSame(
            $command,
            $repository->receivedCommand
        );
    }
}
