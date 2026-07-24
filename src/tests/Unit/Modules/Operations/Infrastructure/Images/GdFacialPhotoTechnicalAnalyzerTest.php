<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Images;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalIssue;
use App\Modules\Operations\Infrastructure\Images\GdFacialPhotoTechnicalAnalyzer;
use GdImage;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class GdFacialPhotoTechnicalAnalyzerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path(
            'framework/testing/facial-photo-analysis'
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

    public function test_it_approves_a_technically_valid_portrait_image(): void
    {
        $path = $this->createCheckerboardJpeg(
            'valid.jpg',
            720,
            900,
            35,
            220
        );

        $analysis = $this->analyzer()
            ->analyze($path);

        $this->assertTrue(
            $analysis->passed,
            json_encode(
                $analysis->toArray(),
                JSON_PRETTY_PRINT
            )
        );

        $this->assertSame(
            'technical-v1',
            $analysis->version
        );

        $this->assertSame(
            720,
            $analysis->metrics['width']
        );

        $this->assertSame(
            900,
            $analysis->metrics['height']
        );

        $this->assertSame(
            'image/jpeg',
            $analysis->metrics['mime_type']
        );

        $this->assertNotEmpty(
            $analysis->metrics['sha256']
        );

        $this->assertGreaterThan(
            18,
            $analysis->metrics[
                'contrast_standard_deviation'
            ]
        );

        $this->assertGreaterThan(
            80,
            $analysis->metrics[
                'sharpness_variance'
            ]
        );
    }

    public function test_it_rejects_low_resolution_and_invalid_proportion(): void
    {
        $small = $this->createCheckerboardJpeg(
            'small.jpg',
            320,
            400,
            35,
            220
        );

        $smallAnalysis = $this->analyzer()
            ->analyze($small);

        $this->assertContains(
            FacialPhotoTechnicalIssue::ResolutionTooLow->value,
            $smallAnalysis->issueCodes()
        );

        $tooTall = $this->createCheckerboardJpeg(
            'too-tall.jpg',
            500,
            1100,
            35,
            220
        );

        $tooTallAnalysis = $this->analyzer()
            ->analyze($tooTall);

        $this->assertContains(
            FacialPhotoTechnicalIssue::AspectRatioInvalid->value,
            $tooTallAnalysis->issueCodes()
        );
    }

    public function test_it_detects_underexposure_and_overexposure(): void
    {
        $dark = $this->createSolidJpeg(
            'dark.jpg',
            720,
            900,
            12
        );

        $darkAnalysis = $this->analyzer()
            ->analyze($dark);

        $this->assertContains(
            FacialPhotoTechnicalIssue::Underexposed->value,
            $darkAnalysis->issueCodes()
        );

        $bright = $this->createSolidJpeg(
            'bright.jpg',
            720,
            900,
            245
        );

        $brightAnalysis = $this->analyzer()
            ->analyze($bright);

        $this->assertContains(
            FacialPhotoTechnicalIssue::Overexposed->value,
            $brightAnalysis->issueCodes()
        );
    }

    public function test_it_detects_low_contrast_and_low_sharpness(): void
    {
        $path = $this->createSolidJpeg(
            'flat.jpg',
            720,
            900,
            128
        );

        $analysis = $this->analyzer()
            ->analyze($path);

        $this->assertContains(
            FacialPhotoTechnicalIssue::LowContrast->value,
            $analysis->issueCodes()
        );

        $this->assertContains(
            FacialPhotoTechnicalIssue::LowSharpness->value,
            $analysis->issueCodes()
        );
    }

    public function test_it_rejects_an_unsupported_image_format(): void
    {
        $path = $this->createGif(
            'unsupported.gif',
            720,
            900
        );

        $analysis = $this->analyzer()
            ->analyze($path);

        $this->assertFalse(
            $analysis->passed
        );

        $this->assertContains(
            FacialPhotoTechnicalIssue::UnsupportedFormat->value,
            $analysis->issueCodes()
        );
    }

    public function test_it_stops_before_decoding_excessive_pixel_dimensions(): void
    {
        config()->set(
            'facial_photos.technical_validation.maximum_pixels',
            1000
        );

        $path = $this->createCheckerboardJpeg(
            'excessive.jpg',
            720,
            900,
            35,
            220
        );

        $analysis = $this->analyzer()
            ->analyze($path);

        $this->assertContains(
            FacialPhotoTechnicalIssue::PixelLimitExceeded->value,
            $analysis->issueCodes()
        );

        $this->assertNull(
            $analysis->metrics['mean_luminance']
        );
    }

    public function test_it_rejects_an_unavailable_file(): void
    {
        $analysis = $this->analyzer()
            ->analyze(
                $this->directory.'/missing.jpg'
            );

        $this->assertSame(
            [
                FacialPhotoTechnicalIssue::FileUnavailable->value,
            ],
            $analysis->issueCodes()
        );
    }

    private function analyzer(): GdFacialPhotoTechnicalAnalyzer
    {
        return new GdFacialPhotoTechnicalAnalyzer;
    }

    private function createCheckerboardJpeg(
        string $filename,
        int $width,
        int $height,
        int $dark,
        int $light
    ): string {
        $image = imagecreatetruecolor(
            $width,
            $height
        );

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        $darkColor = imagecolorallocate(
            $image,
            $dark,
            $dark,
            $dark
        );

        $lightColor = imagecolorallocate(
            $image,
            $light,
            $light,
            $light
        );

        $block = 24;

        for ($y = 0; $y < $height; $y += $block) {
            for ($x = 0; $x < $width; $x += $block) {
                $color = (
                    (
                        intdiv($x, $block)
                        + intdiv($y, $block)
                    ) % 2 === 0
                )
                    ? $darkColor
                    : $lightColor;

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

        return $this->saveJpeg(
            $filename,
            $image
        );
    }

    private function createSolidJpeg(
        string $filename,
        int $width,
        int $height,
        int $value
    ): string {
        $image = imagecreatetruecolor(
            $width,
            $height
        );

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        $color = imagecolorallocate(
            $image,
            $value,
            $value,
            $value
        );

        imagefill(
            $image,
            0,
            0,
            $color
        );

        return $this->saveJpeg(
            $filename,
            $image
        );
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

        $color = imagecolorallocate(
            $image,
            128,
            128,
            128
        );

        imagefill(
            $image,
            0,
            0,
            $color
        );

        $path = $this->directory.'/'.$filename;

        $this->assertTrue(
            imagegif(
                $image,
                $path
            )
        );

        imagedestroy($image);

        return $path;
    }

    private function saveJpeg(
        string $filename,
        GdImage $image
    ): string {
        $path = $this->directory.'/'.$filename;

        $this->assertTrue(
            imagejpeg(
                $image,
                $path,
                92
            )
        );

        imagedestroy($image);

        return $path;
    }
}
