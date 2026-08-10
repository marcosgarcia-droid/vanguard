<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Registration;

use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoCommand;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoException;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoResult;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSubjectType;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RegisterFacialPhotoContractTest extends TestCase
{
    public function test_it_exposes_explicit_supported_subject_types(): void
    {
        self::assertSame(
            'visitor',
            FacialPhotoSubjectType::Visitor->value
        );

        self::assertSame(
            'employee',
            FacialPhotoSubjectType::Employee->value
        );
    }

    public function test_command_preserves_a_generic_employee_subject(): void
    {
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

        self::assertSame(
            FacialPhotoSubjectType::Employee,
            $command->subjectType
        );

        self::assertSame(
            '11111111-1111-4111-8111-111111111111',
            $command->subjectId
        );
    }

    #[DataProvider('invalidSubjectProvider')]
    public function test_command_rejects_an_invalid_subject(
        string $subjectId
    ): void {
        $this->expectException(
            RegisterFacialPhotoException::class
        );

        new RegisterFacialPhotoCommand(
            subjectType: FacialPhotoSubjectType::Employee,
            subjectId: $subjectId,
            absolutePath: '/tmp/employee-face.jpg',
            originalFileName: 'employee-face.jpg',
            expectedSha256: str_repeat('a', 64),
            source: FacialPhotoSource::Webcam,
            confirmationKey: str_repeat('b', 64),
            confirmationContext: 'employee.update.photo_capture',
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSubjectProvider(): array
    {
        return [
            'empty' => [''],
            'spaces' => ['   '],
            'surrounding whitespace' => [' subject-id '],
            'oversized' => [str_repeat('a', 65)],
        ];
    }

    public function test_generic_result_preserves_status_helpers(): void
    {
        $analysis = new FacialPhotoTechnicalAnalysis(
            passed: true,
            metrics: [],
            issues: [],
            version: 'test',
        );

        $pending = new RegisterFacialPhotoResult(
            photoId: 'photo-1',
            status: FacialPhotoStatus::PendingValidation,
            technicalAnalysis: $analysis,
        );

        self::assertTrue(
            $pending->awaitsAdditionalValidation()
        );

        self::assertFalse(
            $pending->isRejected()
        );
    }

    public function test_confirmation_consumption_exception_is_identifiable(): void
    {
        $exception =
            RegisterFacialPhotoException::confirmationAlreadyConsumed();

        self::assertTrue(
            $exception->isConfirmationAlreadyConsumed()
        );
    }
}
