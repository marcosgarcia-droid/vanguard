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
    private const CONFIRMATION_KEY =
        'cccccccccccccccccccccccccccccccc'
        .'cccccccccccccccccccccccccccccccc';

    private const CONFIRMATION_CONTEXT =
        'visitor.test.photo_capture';

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
            confirmationKey: hash(
                'sha256',
                'opaque-receipt'
            ),
            confirmationContext: 'visitor.create.photo_capture',
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

        $this->assertSame(
            hash(
                'sha256',
                'opaque-receipt'
            ),
            $repository->receivedCommand?->confirmationKey
        );

        $this->assertSame(
            'visitor.create.photo_capture',
            $repository->receivedCommand?->confirmationContext
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
            confirmationKey: self::CONFIRMATION_KEY,
            confirmationContext: self::CONFIRMATION_CONTEXT,
        );
    }

    public function test_it_rejects_an_invalid_confirmation_key(): void
    {
        $this->expectException(
            RegisterVisitorFacialPhotoException::class
        );

        $this->expectExceptionMessage(
            'A confirmação da foto facial não é válida. '
                .'Analise a imagem novamente.'
        );

        new RegisterVisitorFacialPhotoCommand(
            visitorId: 'visitor-123',
            absolutePath: '/tmp/visitor-photo.jpg',
            originalFileName: 'visitor-photo.jpg',
            expectedSha256: str_repeat(
                'a',
                64
            ),
            source: FacialPhotoSource::Webcam,
            confirmationKey: 'invalid',
            confirmationContext: self::CONFIRMATION_CONTEXT,
        );
    }

    public function test_it_rejects_an_invalid_confirmation_context(): void
    {
        $this->expectException(
            RegisterVisitorFacialPhotoException::class
        );

        $this->expectExceptionMessage(
            'A confirmação da foto facial não é válida. '
                .'Analise a imagem novamente.'
        );

        new RegisterVisitorFacialPhotoCommand(
            visitorId: 'visitor-123',
            absolutePath: '/tmp/visitor-photo.jpg',
            originalFileName: 'visitor-photo.jpg',
            expectedSha256: str_repeat(
                'a',
                64
            ),
            source: FacialPhotoSource::Webcam,
            confirmationKey: self::CONFIRMATION_KEY,
            confirmationContext: ' ',
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
