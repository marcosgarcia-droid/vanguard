<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Images\Normalization;

use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationException;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationResult;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialPhotoDescriptor;
use InvalidArgumentException;

final readonly class IntelbrasFacialPhotoNormalizer implements FacialPhotoNormalizer
{
    public function __construct(
        private FacialPhotoNormalizer $normalizer
    ) {}

    public function normalize(
        string $absoluteSourcePath
    ): FacialPhotoNormalizationResult {
        $result =
            $this->normalizer->normalize(
                $absoluteSourcePath
            );

        try {
            new IntelbrasFacialPhotoDescriptor(
                sha256: $result->sha256,

                byteLength: $result->sizeBytes,

                width: $result->width,

                height: $result->height,

                mimeType: $result->mimeType,
            );
        } catch (InvalidArgumentException) {
            $this->removeOutput(
                $result
            );

            throw FacialPhotoNormalizationException::invalidOutput();
        }

        return $result;
    }

    private function removeOutput(
        FacialPhotoNormalizationResult $result
    ): void {
        if (
            is_file($result->absolutePath)
            || is_link($result->absolutePath)
        ) {
            @unlink(
                $result->absolutePath
            );
        }
    }
}
