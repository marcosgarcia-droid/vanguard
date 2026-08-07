<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\Normalization;

use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationException;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationResult;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizer;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeProfile;
use App\Modules\Operations\Infrastructure\Images\Normalization\IntelbrasFacialPhotoNormalizer;
use PHPUnit\Framework\TestCase;

final class IntelbrasFacialPhotoNormalizerTest extends TestCase
{
    public function test_it_accepts_metadata_inside_the_documented_intelbras_contract(): void
    {
        $result =
            $this->normalizationResult(
                absolutePath: '/tmp/intelbras-valid.jpg',

                width: 600,

                height: 750,

                sizeBytes: 50_000,
            );

        $normalizer =
            new IntelbrasFacialPhotoNormalizer(
                $this->normalizerStub(
                    $result
                )
            );

        self::assertSame(
            $result,
            $normalizer->normalize(
                '/tmp/source-not-read.jpg'
            )
        );
    }

    public function test_it_rejects_and_removes_an_incompatible_output(): void
    {
        $path = tempnam(
            sys_get_temp_dir(),
            'vanguard-intelbras-'
        );

        self::assertIsString(
            $path
        );

        file_put_contents(
            $path,
            'temporary-output'
        );

        $result =
            $this->normalizationResult(
                absolutePath: $path,

                width: 601,

                height: 750,

                sizeBytes: 50_000,
            );

        $normalizer =
            new IntelbrasFacialPhotoNormalizer(
                $this->normalizerStub(
                    $result
                )
            );

        try {
            $normalizer->normalize(
                '/tmp/source-not-read.jpg'
            );

            self::fail(
                'Saída incompatível deveria ser rejeitada.'
            );
        } catch (
            FacialPhotoNormalizationException
        ) {
            self::assertFileDoesNotExist(
                $path
            );
        }
    }

    private function normalizerStub(
        FacialPhotoNormalizationResult $result
    ): FacialPhotoNormalizer {
        return new class($result) implements FacialPhotoNormalizer
        {
            public function __construct(
                private readonly FacialPhotoNormalizationResult $normalization
            ) {}

            public function normalize(
                string $absoluteSourcePath
            ): FacialPhotoNormalizationResult {
                return $this->normalization;
            }
        };
    }

    private function normalizationResult(
        string $absolutePath,
        int $width,
        int $height,
        int $sizeBytes,
    ): FacialPhotoNormalizationResult {
        return new FacialPhotoNormalizationResult(
            absolutePath: $absolutePath,

            profile: FacialPhotoDerivativeProfile::intelbrasFacialCredential(),

            policyVersion: 'intelbras-facial-credential-v1',

            normalizer: 'spatie-gd',

            normalizerVersion: 'spatie-gd-v1',

            sourceSha256: str_repeat(
                'a',
                64
            ),

            width: $width,

            height: $height,

            mimeType: 'image/jpeg',

            sizeBytes: $sizeBytes,

            sha256: str_repeat(
                'b',
                64
            ),
        );
    }
}
