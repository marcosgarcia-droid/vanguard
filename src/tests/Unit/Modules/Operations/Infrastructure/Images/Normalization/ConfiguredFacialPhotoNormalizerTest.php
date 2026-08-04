<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\Normalization;

use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationException;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationResult;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizer;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeProfile;
use App\Modules\Operations\Infrastructure\Images\Normalization\ConfiguredFacialPhotoNormalizer;
use PHPUnit\Framework\TestCase;

final class ConfiguredFacialPhotoNormalizerTest extends TestCase
{
    public function test_it_fails_closed_while_disabled(): void
    {
        $delegate = $this->delegate();

        $configured = new ConfiguredFacialPhotoNormalizer(
            enabled: false,
            normalizer: $delegate
        );

        try {
            $configured->normalize(
                '/tmp/source.jpg'
            );

            $this->fail(
                'Era esperada uma falha de funcionalidade desativada.'
            );
        } catch (FacialPhotoNormalizationException $exception) {
            $this->assertSame(
                'normalization_disabled',
                $exception->failureCode
            );
        }

        $this->assertFalse(
            $delegate->called
        );
    }

    public function test_it_delegates_when_enabled(): void
    {
        $delegate = $this->delegate();

        $configured = new ConfiguredFacialPhotoNormalizer(
            enabled: true,
            normalizer: $delegate
        );

        $result = $configured->normalize(
            '/tmp/source.jpg'
        );

        $this->assertTrue(
            $delegate->called
        );

        $this->assertSame(
            '/tmp/normalized.jpg',
            $result->absolutePath
        );
    }

    private function delegate(): FacialPhotoNormalizer
    {
        return new class implements FacialPhotoNormalizer
        {
            public bool $called = false;

            public function normalize(
                string $absoluteSourcePath
            ): FacialPhotoNormalizationResult {
                $this->called = true;

                return new FacialPhotoNormalizationResult(
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
            }
        };
    }
}
