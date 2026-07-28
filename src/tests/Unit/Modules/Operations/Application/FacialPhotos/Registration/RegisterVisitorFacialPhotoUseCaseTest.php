<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Registration;

use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoCommand;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoException;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoRepository;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoResult;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoUseCase;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RegisterVisitorFacialPhotoUseCaseTest extends TestCase
{
    public function test_it_delegates_registration_to_the_repository(): void
    {
        $capturedAt = new DateTimeImmutable(
            '2026-07-24 15:30:00'
        );

        $expectedSha256 =
            str_repeat('a', 64);

        $command = new RegisterVisitorFacialPhotoCommand(
            visitorId: 'visitor-123',
            absolutePath: '/tmp/visitor-photo.jpg',
            originalFileName: 'visitor-photo.jpg',
            expectedSha256: $expectedSha256,
            source: FacialPhotoSource::Webcam,
            createdBy: 10,
            capturedAt: $capturedAt,
        );

        $analysis = new FacialPhotoTechnicalAnalysis(
            version: 'technical-v1',
            passed: true,
            metrics: [
                'width' => 720,
                'height' => 900,
            ],
            issues: [],
        );

        $expectedResult =
            new RegisterVisitorFacialPhotoResult(
                photoId: 'photo-123',
                status: FacialPhotoStatus::PendingValidation,
                technicalAnalysis: $analysis,
            );

        $repository = new class($expectedResult) implements RegisterVisitorFacialPhotoRepository
        {
            public ?RegisterVisitorFacialPhotoCommand $receivedCommand = null;

            public function __construct(
                private readonly RegisterVisitorFacialPhotoResult $result,
            ) {}

            public function register(
                RegisterVisitorFacialPhotoCommand $command
            ): RegisterVisitorFacialPhotoResult {
                $this->receivedCommand = $command;

                return $this->result;
            }
        };

        $result = (
            new RegisterVisitorFacialPhotoUseCase(
                $repository
            )
        )->execute($command);

        $this->assertSame(
            $command,
            $repository->receivedCommand
        );

        $this->assertSame(
            $expectedResult,
            $result
        );

        $this->assertSame(
            FacialPhotoSource::Webcam,
            $repository->receivedCommand?->source
        );

        $this->assertSame(
            $capturedAt,
            $repository->receivedCommand?->capturedAt
        );

        $this->assertSame(
            $expectedSha256,
            $repository->receivedCommand?->expectedSha256
        );
    }

    public function test_it_rejects_an_invalid_expected_fingerprint(): void
    {
        $this->expectException(
            RegisterVisitorFacialPhotoException::class
        );

        $this->expectExceptionMessage(
            'A confirmação da foto facial não possui uma assinatura válida. '
                .'Analise a imagem novamente.'
        );

        new RegisterVisitorFacialPhotoCommand(
            visitorId: 'visitor-123',
            absolutePath: '/tmp/visitor-photo.jpg',
            originalFileName: 'visitor-photo.jpg',
            expectedSha256: 'invalid',
            source: FacialPhotoSource::Webcam,
        );
    }

    public function test_technical_pass_awaits_facial_validation(): void
    {
        $result = new RegisterVisitorFacialPhotoResult(
            photoId: 'photo-pending',
            status: FacialPhotoStatus::PendingValidation,
            technicalAnalysis: new FacialPhotoTechnicalAnalysis(
                version: 'technical-v1',
                passed: true,
                metrics: [],
                issues: [],
            ),
        );

        $this->assertTrue(
            $result->awaitsAdditionalValidation()
        );

        $this->assertNotSame(
            FacialPhotoStatus::Approved,
            $result->status
        );

        $this->assertFalse(
            $result->isRejected()
        );
    }

    public function test_failed_technical_analysis_is_rejected(): void
    {
        $result = new RegisterVisitorFacialPhotoResult(
            photoId: 'photo-rejected',
            status: FacialPhotoStatus::Rejected,
            technicalAnalysis: new FacialPhotoTechnicalAnalysis(
                version: 'technical-v1',
                passed: false,
                metrics: [],
                issues: [],
            ),
        );

        $this->assertTrue(
            $result->isRejected()
        );

        $this->assertFalse(
            $result->awaitsAdditionalValidation()
        );

        $this->assertNotSame(
            FacialPhotoStatus::Approved,
            $result->status
        );
    }
}
