<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Normalization;

use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationException;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationResult;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeProfile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FacialPhotoNormalizationContractTest extends TestCase
{
    public function test_result_exposes_only_safe_output_metadata(): void
    {
        $result = new FacialPhotoNormalizationResult(
            absolutePath: '/tmp/normalized.jpg',
            profile: FacialPhotoDerivativeProfile::from(
                'vanguard_normalized'
            ),
            policyVersion: 'vanguard-normalization-v1',
            normalizer: 'spatie-gd',
            normalizerVersion: 'spatie-gd-v1',
            sourceSha256: str_repeat('a', 64),
            width: 800,
            height: 1000,
            mimeType: 'image/jpeg',
            sizeBytes: 120_000,
            sha256: str_repeat('b', 64)
        );

        $this->assertSame(
            [
                'width' => 800,
                'height' => 1000,
                'mime_type' => 'image/jpeg',
                'size_bytes' => 120_000,
                'sha256' => str_repeat('b', 64),
            ],
            $result->outputMetadata()
        );
    }

    public function test_result_rejects_invalid_fingerprints(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new FacialPhotoNormalizationResult(
            absolutePath: '/tmp/normalized.jpg',
            profile: FacialPhotoDerivativeProfile::from(
                'vanguard_normalized'
            ),
            policyVersion: 'vanguard-normalization-v1',
            normalizer: 'spatie-gd',
            normalizerVersion: 'spatie-gd-v1',
            sourceSha256: 'invalid',
            width: 800,
            height: 1000,
            mimeType: 'image/jpeg',
            sizeBytes: 120_000,
            sha256: str_repeat('b', 64)
        );
    }

    public function test_result_requires_jpeg_output(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new FacialPhotoNormalizationResult(
            absolutePath: '/tmp/normalized.png',
            profile: FacialPhotoDerivativeProfile::from(
                'vanguard_normalized'
            ),
            policyVersion: 'vanguard-normalization-v1',
            normalizer: 'spatie-gd',
            normalizerVersion: 'spatie-gd-v1',
            sourceSha256: str_repeat('a', 64),
            width: 800,
            height: 1000,
            mimeType: 'image/png',
            sizeBytes: 120_000,
            sha256: str_repeat('b', 64)
        );
    }

    public function test_exceptions_expose_safe_failure_codes(): void
    {
        $exception =
            FacialPhotoNormalizationException::outputTooLarge();

        $this->assertSame(
            'output_too_large',
            $exception->failureCode
        );

        $this->assertSame(
            'A imagem facial normalizada excede o limite permitido.',
            $exception->getMessage()
        );
    }
}
