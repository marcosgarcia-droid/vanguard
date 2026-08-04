<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Derivatives\Generate;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeException;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeResult;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GenerateFacialPhotoDerivativeContractTest extends TestCase
{
    public function test_command_exposes_a_deterministic_identity(): void
    {
        $first = $this->command();
        $second = $this->command();

        $this->assertSame(
            $first->identity(),
            $second->identity()
        );

        $this->assertSame(
            'vanguard_normalized',
            $first->profile->value
        );
    }

    public function test_command_rejects_invalid_values(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new GenerateFacialPhotoDerivativeCommand(
            photoId: 'invalid',
            profile: 'vanguard_normalized',
            policyVersion: 'vanguard-normalization-v1',
            normalizer: 'spatie-gd',
            normalizerVersion: 'spatie-gd-v1',
        );
    }

    public function test_result_exposes_only_safe_metadata(): void
    {
        $result = new GenerateFacialPhotoDerivativeResult(
            derivativeId: '11111111-1111-4111-8111-111111111111',
            attemptId: '22222222-2222-4222-8222-222222222222',
            status: FacialPhotoDerivativeStatus::Ready,
            reused: false,
            mediaId: 10,
            width: 600,
            height: 900,
            mimeType: 'image/jpeg',
            sizeBytes: 100_000,
            sha256: str_repeat('a', 64),
        );

        $this->assertSame(
            [
                'width' => 600,
                'height' => 900,
                'mime_type' => 'image/jpeg',
                'size_bytes' => 100_000,
                'sha256' => str_repeat('a', 64),
            ],
            $result->outputMetadata()
        );
    }

    public function test_exceptions_expose_safe_failure_codes(): void
    {
        $exception =
            GenerateFacialPhotoDerivativeException::sourceChanged();

        $this->assertSame(
            'source_changed',
            $exception->failureCode
        );

        $this->assertSame(
            'O arquivo original da foto facial foi alterado.',
            $exception->getMessage()
        );
    }

    private function command(): GenerateFacialPhotoDerivativeCommand
    {
        return new GenerateFacialPhotoDerivativeCommand(
            photoId: '11111111-1111-4111-8111-111111111111',
            profile: 'vanguard_normalized',
            policyVersion: 'vanguard-normalization-v1',
            normalizer: 'spatie-gd',
            normalizerVersion: 'spatie-gd-v1',
            requestedBy: 10,
            requesterName: 'Operador sintético',
        );
    }
}
