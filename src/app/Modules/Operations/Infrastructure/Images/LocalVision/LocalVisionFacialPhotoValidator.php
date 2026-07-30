<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Images\LocalVision;

use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use InvalidArgumentException;

final readonly class LocalVisionFacialPhotoValidator implements FacialPhotoValidator
{
    public const VALIDATOR = 'local-vision-facial-validator';

    public const VERSION = 'foundation-v1';

    public function validate(
        string $absolutePath
    ): FacialPhotoValidationResult {
        $this->assertAbsolutePath($absolutePath);

        /*
         * A fundação não simula aprovação ou reprovação.
         * Até existir o serviço de visão, o resultado é inconclusivo.
         */
        return new FacialPhotoValidationResult(
            validator: self::VALIDATOR,
            version: self::VERSION,
            decision: FacialPhotoValidationDecision::Inconclusive,
            faceCount: 0,
            metrics: [
                'available' => false,
                'transport_configured' => false,
            ],
            issues: [
                FacialPhotoValidationIssue::ValidatorUnavailable,
            ],
        );
    }

    private function assertAbsolutePath(
        string $absolutePath
    ): void {
        if (
            trim($absolutePath) === ''
            || ! str_starts_with(
                $absolutePath,
                DIRECTORY_SEPARATOR
            )
        ) {
            throw new InvalidArgumentException(
                'O validador facial local exige um caminho absoluto.'
            );
        }
    }
}
