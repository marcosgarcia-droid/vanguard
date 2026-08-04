<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Images\Normalization;

use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationException;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationResult;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizer;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeProfile;
use InvalidArgumentException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image;
use Throwable;

final readonly class SpatieGdFacialPhotoNormalizer implements FacialPhotoNormalizer
{
    /**
     * @param  list<string>  $allowedMimeTypes
     */
    public function __construct(
        private FacialPhotoDerivativeProfile $profile,
        private string $policyVersion,
        private string $normalizer,
        private string $normalizerVersion,
        private array $allowedMimeTypes,
        private int $maximumSourceSizeBytes,
        private int $maximumSourcePixels,
        private int $maximumWidth,
        private int $maximumHeight,
        private int $jpegQuality,
        private int $maximumOutputSizeBytes,
        private string $temporaryDirectory
    ) {
        $this->validateConfiguration();
    }

    public function normalize(
        string $absoluteSourcePath
    ): FacialPhotoNormalizationResult {
        $outputPath = null;

        try {
            $source = $this->inspectSource(
                $absoluteSourcePath
            );

            $outputPath = $this->reserveOutputPath();

            Image::useImageDriver(ImageDriver::Gd)
                ->loadFile($absoluteSourcePath)
                ->fit(
                    Fit::Max,
                    $this->maximumWidth,
                    $this->maximumHeight
                )
                ->background('#ffffff')
                ->format('jpeg')
                ->quality($this->jpegQuality)
                ->save($outputPath);

            if (! @chmod($outputPath, 0600)) {
                throw FacialPhotoNormalizationException::outputWriteFailed();
            }

            return $this->inspectOutput(
                $outputPath,
                $source['sha256']
            );
        } catch (FacialPhotoNormalizationException $exception) {
            $this->removeOutput($outputPath);

            throw $exception;
        } catch (Throwable $throwable) {
            $this->removeOutput($outputPath);

            throw FacialPhotoNormalizationException::processingFailed(
                $throwable
            );
        }
    }

    /**
     * @return array{
     *     width: int,
     *     height: int,
     *     mime_type: string,
     *     size_bytes: int,
     *     sha256: string
     * }
     */
    private function inspectSource(
        string $absoluteSourcePath
    ): array {
        if (
            ! is_file($absoluteSourcePath)
            || ! is_readable($absoluteSourcePath)
        ) {
            throw FacialPhotoNormalizationException::sourceUnavailable();
        }

        $sizeBytes = filesize($absoluteSourcePath);

        if (! is_int($sizeBytes) || $sizeBytes < 1) {
            throw FacialPhotoNormalizationException::sourceUnavailable();
        }

        if ($sizeBytes > $this->maximumSourceSizeBytes) {
            throw FacialPhotoNormalizationException::sourceTooLarge();
        }

        $information = @getimagesize(
            $absoluteSourcePath
        );

        if (! is_array($information)) {
            throw FacialPhotoNormalizationException::decodeFailed();
        }

        $width = (int) ($information[0] ?? 0);
        $height = (int) ($information[1] ?? 0);
        $mimeType = is_string(
            $information['mime'] ?? null
        )
            ? $information['mime']
            : '';

        if (
            $width < 1
            || $height < 1
            || $mimeType === ''
        ) {
            throw FacialPhotoNormalizationException::decodeFailed();
        }

        if (
            ! in_array(
                $mimeType,
                $this->allowedMimeTypes,
                true
            )
        ) {
            throw FacialPhotoNormalizationException::unsupportedFormat();
        }

        if (
            ($width * $height)
            > $this->maximumSourcePixels
        ) {
            throw FacialPhotoNormalizationException::pixelLimitExceeded();
        }

        $sha256 = hash_file(
            'sha256',
            $absoluteSourcePath
        );

        if (! is_string($sha256)) {
            throw FacialPhotoNormalizationException::decodeFailed();
        }

        return [
            'width' => $width,
            'height' => $height,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'sha256' => $sha256,
        ];
    }

    private function reserveOutputPath(): string
    {
        $directory = rtrim(
            $this->temporaryDirectory,
            DIRECTORY_SEPARATOR
        );

        if (is_link($directory)) {
            throw FacialPhotoNormalizationException::temporaryDirectoryUnavailable();
        }

        if (! is_dir($directory)) {
            if (
                file_exists($directory)
                || ! @mkdir(
                    $directory,
                    0700,
                    true
                )
            ) {
                throw FacialPhotoNormalizationException::temporaryDirectoryUnavailable();
            }
        }

        if (! is_writable($directory)) {
            throw FacialPhotoNormalizationException::temporaryDirectoryUnavailable();
        }

        @chmod($directory, 0700);

        try {
            $filename = bin2hex(
                random_bytes(20)
            ).'.jpg';
        } catch (Throwable $throwable) {
            throw FacialPhotoNormalizationException::temporaryDirectoryUnavailable(
                $throwable
            );
        }

        $path = $directory
            .DIRECTORY_SEPARATOR
            .$filename;

        $handle = @fopen(
            $path,
            'x+b'
        );

        if ($handle === false) {
            throw FacialPhotoNormalizationException::outputWriteFailed();
        }

        fclose($handle);

        if (! @chmod($path, 0600)) {
            @unlink($path);

            throw FacialPhotoNormalizationException::outputWriteFailed();
        }

        return $path;
    }

    private function inspectOutput(
        string $absoluteOutputPath,
        string $sourceSha256
    ): FacialPhotoNormalizationResult {
        clearstatcache(
            true,
            $absoluteOutputPath
        );

        if (
            ! is_file($absoluteOutputPath)
            || ! is_readable($absoluteOutputPath)
        ) {
            throw FacialPhotoNormalizationException::invalidOutput();
        }

        $sizeBytes = filesize(
            $absoluteOutputPath
        );

        if (! is_int($sizeBytes) || $sizeBytes < 1) {
            throw FacialPhotoNormalizationException::invalidOutput();
        }

        if ($sizeBytes > $this->maximumOutputSizeBytes) {
            throw FacialPhotoNormalizationException::outputTooLarge();
        }

        $information = @getimagesize(
            $absoluteOutputPath
        );

        if (! is_array($information)) {
            throw FacialPhotoNormalizationException::invalidOutput();
        }

        $width = (int) ($information[0] ?? 0);
        $height = (int) ($information[1] ?? 0);
        $mimeType = is_string(
            $information['mime'] ?? null
        )
            ? $information['mime']
            : '';

        if (
            $width < 1
            || $height < 1
            || $width > $this->maximumWidth
            || $height > $this->maximumHeight
            || $mimeType !== 'image/jpeg'
        ) {
            throw FacialPhotoNormalizationException::invalidOutput();
        }

        $sha256 = hash_file(
            'sha256',
            $absoluteOutputPath
        );

        if (! is_string($sha256)) {
            throw FacialPhotoNormalizationException::invalidOutput();
        }

        return new FacialPhotoNormalizationResult(
            absolutePath: $absoluteOutputPath,
            profile: $this->profile,
            policyVersion: $this->policyVersion,
            normalizer: $this->normalizer,
            normalizerVersion: $this->normalizerVersion,
            sourceSha256: $sourceSha256,
            width: $width,
            height: $height,
            mimeType: $mimeType,
            sizeBytes: $sizeBytes,
            sha256: $sha256
        );
    }

    private function removeOutput(
        ?string $absoluteOutputPath
    ): void {
        if (
            $absoluteOutputPath !== null
            && (
                is_file($absoluteOutputPath)
                || is_link($absoluteOutputPath)
            )
        ) {
            @unlink($absoluteOutputPath);
        }
    }

    private function validateConfiguration(): void
    {
        $this->validateToken(
            $this->policyVersion,
            'policyVersion',
            50
        );

        $this->validateToken(
            $this->normalizer,
            'normalizer',
            100
        );

        $this->validateToken(
            $this->normalizerVersion,
            'normalizerVersion',
            50
        );

        if (
            ! array_is_list($this->allowedMimeTypes)
            || $this->allowedMimeTypes === []
            || array_unique($this->allowedMimeTypes)
                !== $this->allowedMimeTypes
        ) {
            throw new InvalidArgumentException(
                'Os formatos permitidos da normalização são inválidos.'
            );
        }

        foreach ($this->allowedMimeTypes as $mimeType) {
            if (
                ! in_array(
                    $mimeType,
                    [
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                    ],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'A normalização contém um formato não suportado.'
                );
            }
        }

        if (
            $this->maximumSourceSizeBytes < 1
            || $this->maximumSourcePixels < 1
            || $this->maximumWidth < 1
            || $this->maximumHeight < 1
            || $this->maximumOutputSizeBytes < 1
            || $this->jpegQuality < 1
            || $this->jpegQuality > 100
        ) {
            throw new InvalidArgumentException(
                'A configuração numérica da normalização é inválida.'
            );
        }

        if (
            $this->temporaryDirectory === ''
            || ! str_starts_with(
                $this->temporaryDirectory,
                DIRECTORY_SEPARATOR
            )
        ) {
            throw new InvalidArgumentException(
                'O diretório temporário da normalização deve ser absoluto.'
            );
        }
    }

    private function validateToken(
        string $value,
        string $field,
        int $maximumLength
    ): void {
        if (
            mb_strlen($value) > $maximumLength
            || preg_match(
                '/\A[a-z0-9][a-z0-9._-]*\z/',
                $value
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                "A configuração {$field} é inválida."
            );
        }
    }
}
