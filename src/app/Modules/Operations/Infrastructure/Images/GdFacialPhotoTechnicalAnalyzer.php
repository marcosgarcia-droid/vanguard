<?php

namespace App\Modules\Operations\Infrastructure\Images;

use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\FacialPhotoTechnicalAnalyzer;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalIssue;
use GdImage;

final class GdFacialPhotoTechnicalAnalyzer implements FacialPhotoTechnicalAnalyzer
{
    public function analyze(
        string $absolutePath
    ): FacialPhotoTechnicalAnalysis {
        $version = (string) config(
            'facial_photos.technical_validation.version',
            'technical-v1'
        );

        $issues = [];

        $addIssue = static function (
            FacialPhotoTechnicalIssue $issue
        ) use (&$issues): void {
            $issues[$issue->value] = $issue;
        };

        $metrics = [
            'width' => null,
            'height' => null,
            'mime_type' => null,
            'size_bytes' => null,
            'sha256' => null,
            'pixel_count' => null,
            'height_width_ratio' => null,
            'mean_luminance' => null,
            'contrast_standard_deviation' => null,
            'sharpness_variance' => null,
        ];

        if (
            ! is_file($absolutePath)
            || ! is_readable($absolutePath)
        ) {
            $addIssue(
                FacialPhotoTechnicalIssue::FileUnavailable
            );

            return $this->result(
                $version,
                $metrics,
                $issues
            );
        }

        $size = filesize($absolutePath);

        if (is_int($size)) {
            $metrics['size_bytes'] = $size;
        }

        $hash = hash_file(
            'sha256',
            $absolutePath
        );

        if (is_string($hash)) {
            $metrics['sha256'] = $hash;
        }

        $imageInformation = @getimagesize(
            $absolutePath
        );

        if (! is_array($imageInformation)) {
            $addIssue(
                FacialPhotoTechnicalIssue::DecodeFailed
            );

            return $this->result(
                $version,
                $metrics,
                $issues
            );
        }

        $width = (int) ($imageInformation[0] ?? 0);
        $height = (int) ($imageInformation[1] ?? 0);

        $mimeType = is_string(
            $imageInformation['mime'] ?? null
        )
            ? $imageInformation['mime']
            : null;

        $pixelCount = $width * $height;

        $heightWidthRatio = $width > 0
            ? $height / $width
            : null;

        $metrics['width'] = $width;
        $metrics['height'] = $height;
        $metrics['mime_type'] = $mimeType;
        $metrics['pixel_count'] = $pixelCount;
        $metrics['height_width_ratio'] =
            $heightWidthRatio !== null
                ? round($heightWidthRatio, 4)
                : null;

        $allowedMimeTypes = config(
            'facial_photos.technical_validation.allowed_mime_types',
            []
        );

        if (
            ! is_array($allowedMimeTypes)
            || ! in_array(
                $mimeType,
                $allowedMimeTypes,
                true
            )
        ) {
            $addIssue(
                FacialPhotoTechnicalIssue::UnsupportedFormat
            );
        }

        $maximumOriginalSize = (int) config(
            'facial_photos.technical_validation.maximum_original_size_bytes',
            5 * 1024 * 1024
        );

        if (
            is_int($size)
            && $size > $maximumOriginalSize
        ) {
            $addIssue(
                FacialPhotoTechnicalIssue::OriginalFileTooLarge
            );
        }

        $maximumPixels = (int) config(
            'facial_photos.technical_validation.maximum_pixels',
            20_000_000
        );

        if ($pixelCount > $maximumPixels) {
            $addIssue(
                FacialPhotoTechnicalIssue::PixelLimitExceeded
            );
        }

        $minimumWidth = (int) config(
            'facial_photos.technical_validation.minimum_width',
            500
        );

        $minimumHeight = (int) config(
            'facial_photos.technical_validation.minimum_height',
            500
        );

        if (
            $width < $minimumWidth
            || $height < $minimumHeight
        ) {
            $addIssue(
                FacialPhotoTechnicalIssue::ResolutionTooLow
            );
        }

        $minimumRatio = (float) config(
            'facial_photos.technical_validation.minimum_height_width_ratio',
            1.0
        );

        $maximumRatio = (float) config(
            'facial_photos.technical_validation.maximum_height_width_ratio',
            2.0
        );

        if (
            $heightWidthRatio === null
            || $heightWidthRatio < $minimumRatio
            || $heightWidthRatio > $maximumRatio
        ) {
            $addIssue(
                FacialPhotoTechnicalIssue::AspectRatioInvalid
            );
        }

        if (
            isset(
                $issues[
                    FacialPhotoTechnicalIssue::UnsupportedFormat->value
                ]
            )
            || isset(
                $issues[
                    FacialPhotoTechnicalIssue::PixelLimitExceeded->value
                ]
            )
        ) {
            return $this->result(
                $version,
                $metrics,
                $issues
            );
        }

        $image = $this->decode(
            $absolutePath,
            $mimeType
        );

        if (! $image instanceof GdImage) {
            $addIssue(
                FacialPhotoTechnicalIssue::DecodeFailed
            );

            return $this->result(
                $version,
                $metrics,
                $issues
            );
        }

        $sample = $this->createSample(
            $image,
            $width,
            $height
        );

        imagedestroy($image);

        if (! $sample instanceof GdImage) {
            $addIssue(
                FacialPhotoTechnicalIssue::DecodeFailed
            );

            return $this->result(
                $version,
                $metrics,
                $issues
            );
        }

        $sampleMetrics = $this->measureSample(
            $sample
        );

        imagedestroy($sample);

        $metrics = array_merge(
            $metrics,
            $sampleMetrics
        );

        $meanLuminance =
            (float) $sampleMetrics['mean_luminance'];

        $minimumLuminance = (float) config(
            'facial_photos.technical_validation.minimum_mean_luminance',
            45.0
        );

        $maximumLuminance = (float) config(
            'facial_photos.technical_validation.maximum_mean_luminance',
            220.0
        );

        if ($meanLuminance < $minimumLuminance) {
            $addIssue(
                FacialPhotoTechnicalIssue::Underexposed
            );
        }

        if ($meanLuminance > $maximumLuminance) {
            $addIssue(
                FacialPhotoTechnicalIssue::Overexposed
            );
        }

        $minimumContrast = (float) config(
            'facial_photos.technical_validation.minimum_contrast_standard_deviation',
            18.0
        );

        if (
            (float) $sampleMetrics[
                'contrast_standard_deviation'
            ] < $minimumContrast
        ) {
            $addIssue(
                FacialPhotoTechnicalIssue::LowContrast
            );
        }

        $minimumSharpness = (float) config(
            'facial_photos.technical_validation.minimum_sharpness_variance',
            80.0
        );

        if (
            (float) $sampleMetrics[
                'sharpness_variance'
            ] < $minimumSharpness
        ) {
            $addIssue(
                FacialPhotoTechnicalIssue::LowSharpness
            );
        }

        return $this->result(
            $version,
            $metrics,
            $issues
        );
    }

    private function decode(
        string $absolutePath,
        ?string $mimeType
    ): GdImage|false {
        return match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg(
                $absolutePath
            ),
            'image/png' => @imagecreatefrompng(
                $absolutePath
            ),
            'image/webp' => @imagecreatefromwebp(
                $absolutePath
            ),
            default => false,
        };
    }

    private function createSample(
        GdImage $source,
        int $width,
        int $height
    ): GdImage|false {
        $maximumDimension = max(
            32,
            (int) config(
                'facial_photos.technical_validation.sample_maximum_dimension',
                320
            )
        );

        $scale = min(
            1.0,
            $maximumDimension / max(
                1,
                $width,
                $height
            )
        );

        $sampleWidth = max(
            1,
            (int) round($width * $scale)
        );

        $sampleHeight = max(
            1,
            (int) round($height * $scale)
        );

        $sample = imagecreatetruecolor(
            $sampleWidth,
            $sampleHeight
        );

        if (! $sample instanceof GdImage) {
            return false;
        }

        $copied = imagecopyresampled(
            $sample,
            $source,
            0,
            0,
            0,
            0,
            $sampleWidth,
            $sampleHeight,
            $width,
            $height
        );

        if (! $copied) {
            imagedestroy($sample);

            return false;
        }

        return $sample;
    }

    /**
     * @return array{
     *     mean_luminance: float,
     *     contrast_standard_deviation: float,
     *     sharpness_variance: float
     * }
     */
    private function measureSample(
        GdImage $image
    ): array {
        $width = imagesx($image);
        $height = imagesy($image);

        $gray = [];
        $sum = 0.0;
        $sumOfSquares = 0.0;
        $count = 0;

        for ($y = 0; $y < $height; $y++) {
            $gray[$y] = [];

            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat(
                    $image,
                    $x,
                    $y
                );

                $red = ($color >> 16) & 0xFF;
                $green = ($color >> 8) & 0xFF;
                $blue = $color & 0xFF;

                $luminance =
                    (0.2126 * $red)
                    + (0.7152 * $green)
                    + (0.0722 * $blue);

                $gray[$y][$x] = $luminance;

                $sum += $luminance;
                $sumOfSquares += $luminance ** 2;
                $count++;
            }
        }

        $mean = $count > 0
            ? $sum / $count
            : 0.0;

        $variance = $count > 0
            ? max(
                0.0,
                ($sumOfSquares / $count)
                    - ($mean ** 2)
            )
            : 0.0;

        $laplacianSum = 0.0;
        $laplacianSumOfSquares = 0.0;
        $laplacianCount = 0;

        for ($y = 1; $y < $height - 1; $y++) {
            for ($x = 1; $x < $width - 1; $x++) {
                $laplacian =
                    (4 * $gray[$y][$x])
                    - $gray[$y][$x - 1]
                    - $gray[$y][$x + 1]
                    - $gray[$y - 1][$x]
                    - $gray[$y + 1][$x];

                $laplacianSum += $laplacian;
                $laplacianSumOfSquares +=
                    $laplacian ** 2;

                $laplacianCount++;
            }
        }

        $laplacianMean = $laplacianCount > 0
            ? $laplacianSum / $laplacianCount
            : 0.0;

        $sharpnessVariance =
            $laplacianCount > 0
                ? max(
                    0.0,
                    (
                        $laplacianSumOfSquares
                        / $laplacianCount
                    ) - ($laplacianMean ** 2)
                )
                : 0.0;

        return [
            'mean_luminance' => round(
                $mean,
                4
            ),
            'contrast_standard_deviation' => round(
                sqrt($variance),
                4
            ),
            'sharpness_variance' => round(
                $sharpnessVariance,
                4
            ),
        ];
    }

    /**
     * @param  array<string, int|float|string|null>  $metrics
     * @param  array<string, FacialPhotoTechnicalIssue>  $issues
     */
    private function result(
        string $version,
        array $metrics,
        array $issues
    ): FacialPhotoTechnicalAnalysis {
        $normalizedIssues = array_values(
            $issues
        );

        return new FacialPhotoTechnicalAnalysis(
            version: $version,
            passed: $normalizedIssues === [],
            metrics: $metrics,
            issues: $normalizedIssues,
        );
    }
}
