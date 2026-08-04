<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\Normalization;

use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationException;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeProfile;
use App\Modules\Operations\Infrastructure\Images\Normalization\SpatieGdFacialPhotoNormalizer;
use GdImage;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class SpatieGdFacialPhotoNormalizerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path(
            'framework/testing/facial-photo-normalization'
        );

        File::deleteDirectory(
            $this->directory
        );

        File::ensureDirectoryExists(
            $this->directory
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(
            $this->directory
        );

        parent::tearDown();
    }

    public function test_it_normalizes_supported_formats_to_private_jpeg(): void
    {
        $sources = [
            $this->createImage(
                'source.jpg',
                'jpeg',
                600,
                900
            ),
            $this->createImage(
                'source.png',
                'png',
                600,
                900
            ),
            $this->createImage(
                'source.webp',
                'webp',
                600,
                900
            ),
        ];

        foreach ($sources as $source) {
            $sourceHash = hash_file(
                'sha256',
                $source
            );

            $result = $this->normalizer(
                maximumWidth: 300,
                maximumHeight: 400
            )->normalize($source);

            $this->assertFileExists(
                $result->absolutePath
            );

            $this->assertSame(
                'image/jpeg',
                $result->mimeType
            );

            $this->assertLessThanOrEqual(
                300,
                $result->width
            );

            $this->assertLessThanOrEqual(
                400,
                $result->height
            );

            $this->assertSame(
                $sourceHash,
                $result->sourceSha256
            );

            $this->assertSame(
                hash_file(
                    'sha256',
                    $result->absolutePath
                ),
                $result->sha256
            );

            clearstatcache(
                true,
                $result->absolutePath
            );

            $this->assertSame(
                0600,
                fileperms(
                    $result->absolutePath
                ) & 0777
            );
        }
    }

    public function test_it_is_deterministic_for_the_same_source_and_policy(): void
    {
        $source = $this->createImage(
            'deterministic.png',
            'png',
            720,
            900
        );

        $normalizer = $this->normalizer(
            maximumWidth: 600,
            maximumHeight: 800
        );

        $first = $normalizer->normalize(
            $source
        );

        $second = $normalizer->normalize(
            $source
        );

        $this->assertNotSame(
            $first->absolutePath,
            $second->absolutePath
        );

        $this->assertSame(
            $first->sha256,
            $second->sha256
        );

        $this->assertSame(
            file_get_contents(
                $first->absolutePath
            ),
            file_get_contents(
                $second->absolutePath
            )
        );
    }

    public function test_it_never_upscales_the_source(): void
    {
        $source = $this->createImage(
            'small.jpg',
            'jpeg',
            240,
            320
        );

        $result = $this->normalizer(
            maximumWidth: 1200,
            maximumHeight: 1600
        )->normalize($source);

        $this->assertSame(
            240,
            $result->width
        );

        $this->assertSame(
            320,
            $result->height
        );
    }

    public function test_it_applies_exif_orientation_and_removes_metadata(): void
    {
        $source = $this->createImage(
            'oriented.jpg',
            'jpeg',
            120,
            80
        );

        $this->injectExifOrientation(
            $source,
            6
        );

        $sourceExif = @exif_read_data(
            $source
        );

        $this->assertIsArray(
            $sourceExif
        );

        $this->assertSame(
            6,
            $sourceExif['Orientation'] ?? null
        );

        $result = $this->normalizer(
            maximumWidth: 1000,
            maximumHeight: 1000
        )->normalize($source);

        $this->assertSame(
            80,
            $result->width
        );

        $this->assertSame(
            120,
            $result->height
        );

        $outputExif = @exif_read_data(
            $result->absolutePath
        );

        $this->assertTrue(
            $outputExif === false
            || ! isset($outputExif['Orientation'])
        );
    }

    public function test_it_rejects_missing_corrupt_and_unsupported_sources(): void
    {
        $normalizer = $this->normalizer();

        $this->assertFailureCode(
            'source_unavailable',
            fn () => $normalizer->normalize(
                $this->directory.'/missing.jpg'
            )
        );

        $corrupt = $this->directory
            .'/corrupt.jpg';

        file_put_contents(
            $corrupt,
            'not-an-image'
        );

        $this->assertFailureCode(
            'decode_failed',
            fn () => $normalizer->normalize(
                $corrupt
            )
        );

        $unsupported = $this->createGif(
            'unsupported.gif',
            300,
            400
        );

        $this->assertFailureCode(
            'unsupported_format',
            fn () => $normalizer->normalize(
                $unsupported
            )
        );
    }

    public function test_it_enforces_source_size_and_pixel_limits(): void
    {
        $source = $this->createImage(
            'limits.jpg',
            'jpeg',
            300,
            400
        );

        $this->assertFailureCode(
            'source_too_large',
            fn () => $this->normalizer(
                maximumSourceSizeBytes: 1
            )->normalize($source)
        );

        $this->assertFailureCode(
            'pixel_limit_exceeded',
            fn () => $this->normalizer(
                maximumSourcePixels: 1000
            )->normalize($source)
        );
    }

    public function test_it_removes_the_temporary_output_when_it_is_too_large(): void
    {
        $source = $this->createImage(
            'output-limit.png',
            'png',
            600,
            900
        );

        $outputDirectory = $this->directory
            .'/output-too-large';

        $this->assertFailureCode(
            'output_too_large',
            fn () => $this->normalizer(
                maximumOutputSizeBytes: 1,
                temporaryDirectory: $outputDirectory
            )->normalize($source)
        );

        $this->assertSame(
            [],
            File::files(
                $outputDirectory
            )
        );
    }

    public function test_it_fails_safely_when_the_temporary_directory_is_invalid(): void
    {
        $source = $this->createImage(
            'directory.jpg',
            'jpeg',
            300,
            400
        );

        $invalidDirectory = $this->directory
            .'/not-a-directory';

        file_put_contents(
            $invalidDirectory,
            'occupied'
        );

        $this->assertFailureCode(
            'temporary_directory_unavailable',
            fn () => $this->normalizer(
                temporaryDirectory: $invalidDirectory
            )->normalize($source)
        );
    }

    private function normalizer(
        int $maximumSourceSizeBytes = 5_242_880,
        int $maximumSourcePixels = 20_000_000,
        int $maximumWidth = 1200,
        int $maximumHeight = 1600,
        int $maximumOutputSizeBytes = 2_097_152,
        ?string $temporaryDirectory = null
    ): SpatieGdFacialPhotoNormalizer {
        return new SpatieGdFacialPhotoNormalizer(
            profile: FacialPhotoDerivativeProfile::from(
                'vanguard_normalized'
            ),
            policyVersion: 'vanguard-normalization-v1',
            normalizer: 'spatie-gd',
            normalizerVersion: 'spatie-gd-v1',
            allowedMimeTypes: [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            maximumSourceSizeBytes: $maximumSourceSizeBytes,
            maximumSourcePixels: $maximumSourcePixels,
            maximumWidth: $maximumWidth,
            maximumHeight: $maximumHeight,
            jpegQuality: 90,
            maximumOutputSizeBytes: $maximumOutputSizeBytes,
            temporaryDirectory: $temporaryDirectory
                ?? $this->directory.'/outputs'
        );
    }

    private function createImage(
        string $filename,
        string $format,
        int $width,
        int $height
    ): string {
        $image = imagecreatetruecolor(
            $width,
            $height
        );

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        $dark = imagecolorallocate(
            $image,
            30,
            60,
            90
        );

        $light = imagecolorallocate(
            $image,
            210,
            225,
            240
        );

        $block = 24;

        for ($y = 0; $y < $height; $y += $block) {
            for ($x = 0; $x < $width; $x += $block) {
                $color = (
                    intdiv($x, $block)
                    + intdiv($y, $block)
                ) % 2 === 0
                    ? $dark
                    : $light;

                imagefilledrectangle(
                    $image,
                    $x,
                    $y,
                    min(
                        $width - 1,
                        $x + $block - 1
                    ),
                    min(
                        $height - 1,
                        $y + $block - 1
                    ),
                    $color
                );
            }
        }

        $path = $this->directory
            .DIRECTORY_SEPARATOR
            .$filename;

        $saved = match ($format) {
            'jpeg' => imagejpeg(
                $image,
                $path,
                92
            ),
            'png' => imagepng(
                $image,
                $path
            ),
            'webp' => imagewebp(
                $image,
                $path,
                92
            ),
            default => false,
        };

        imagedestroy($image);

        $this->assertTrue(
            $saved
        );

        return $path;
    }

    private function createGif(
        string $filename,
        int $width,
        int $height
    ): string {
        $image = imagecreatetruecolor(
            $width,
            $height
        );

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        $path = $this->directory
            .DIRECTORY_SEPARATOR
            .$filename;

        $this->assertTrue(
            imagegif(
                $image,
                $path
            )
        );

        imagedestroy($image);

        return $path;
    }

    private function injectExifOrientation(
        string $path,
        int $orientation
    ): void {
        $jpeg = file_get_contents(
            $path
        );

        $this->assertIsString(
            $jpeg
        );

        $tiff = 'II'
            .pack('v', 42)
            .pack('V', 8)
            .pack('v', 1)
            .pack('v', 0x0112)
            .pack('v', 3)
            .pack('V', 1)
            .pack('v', $orientation)
            ."\0\0"
            .pack('V', 0);

        $payload = "Exif\0\0".$tiff;

        $segment = "\xFF\xE1"
            .pack(
                'n',
                strlen($payload) + 2
            )
            .$payload;

        file_put_contents(
            $path,
            substr($jpeg, 0, 2)
                .$segment
                .substr($jpeg, 2)
        );
    }

    private function assertFailureCode(
        string $expectedCode,
        callable $callback
    ): void {
        try {
            $callback();

            $this->fail(
                "Era esperada a falha {$expectedCode}."
            );
        } catch (FacialPhotoNormalizationException $exception) {
            $this->assertSame(
                $expectedCode,
                $exception->failureCode
            );
        }
    }
}
