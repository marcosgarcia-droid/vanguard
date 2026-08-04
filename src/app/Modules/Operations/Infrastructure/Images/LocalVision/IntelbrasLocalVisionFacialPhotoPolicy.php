<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Images\LocalVision;

use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoAnalysis;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoPolicy;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoPolicyResult;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use InvalidArgumentException;

final readonly class IntelbrasLocalVisionFacialPhotoPolicy implements LocalVisionFacialPhotoPolicy
{
    /**
     * Esta versão representa aprovação de qualidade local no VANGUARD.
     *
     * Ela não declara compatibilidade operacional com qualquer modelo ou
     * firmware de equipamento Intelbras. Essa compatibilidade continua
     * protegida pelo catálogo específico de modelo e firmware.
     */
    public const VERSION = 'vanguard-local-quality-v1';

    public function __construct(
        private float $minimumFaceRatio,
        private float $maximumFaceRatio,
    ) {
        if (
            ! is_finite($this->minimumFaceRatio)
            || ! is_finite($this->maximumFaceRatio)
            || $this->minimumFaceRatio <= 0
            || $this->maximumFaceRatio > 1
            || $this->minimumFaceRatio >= $this->maximumFaceRatio
        ) {
            throw new InvalidArgumentException(
                'Os limites de proporção facial da política local são inválidos.'
            );
        }
    }

    public function decide(
        LocalVisionFacialPhotoAnalysis $analysis
    ): LocalVisionFacialPhotoPolicyResult {
        if ($analysis->faceCount === 0) {
            return $this->rejected(
                FacialPhotoValidationIssue::NoFaceDetected
            );
        }

        if ($analysis->faceCount > 1) {
            return $this->rejected(
                FacialPhotoValidationIssue::MultipleFacesDetected
            );
        }

        $faceRatio = $this->ratioMetric(
            $analysis->metrics,
            'face_ratio'
        );

        $centered = $this->booleanMetric(
            $analysis->metrics,
            'centered'
        );

        $frontal = $this->booleanMetric(
            $analysis->metrics,
            'frontal'
        );

        $eyesOpen = $this->booleanMetric(
            $analysis->metrics,
            'eyes_open'
        );

        /*
         * O motor atual declara explicitamente que ainda não produz
         * evidência confiável de oclusão. Por isso null ou ausência dessa
         * métrica significam "não suportada", e não resposta inválida.
         */
        $occluded = $analysis->metrics['occluded'] ?? null;

        if (
            $faceRatio === null
            || $centered === null
            || $frontal === null
            || $eyesOpen === null
            || (
                $occluded !== null
                && ! is_bool($occluded)
            )
        ) {
            return $this->inconclusive(
                FacialPhotoValidationIssue::InvalidValidatorResponse
            );
        }

        $issues = [];

        if ($faceRatio < $this->minimumFaceRatio) {
            $issues[] = FacialPhotoValidationIssue::FaceTooSmall;
        }

        if ($faceRatio > $this->maximumFaceRatio) {
            $issues[] = FacialPhotoValidationIssue::FaceTooLarge;
        }

        if (! $centered) {
            $issues[] = FacialPhotoValidationIssue::FaceNotCentered;
        }

        if (! $frontal) {
            $issues[] = FacialPhotoValidationIssue::HeadPoseInvalid;
        }

        if (! $eyesOpen) {
            $issues[] = FacialPhotoValidationIssue::EyesNotVisible;
        }

        if ($occluded === true) {
            $issues[] = FacialPhotoValidationIssue::FaceOccluded;
        }

        if ($issues !== []) {
            return new LocalVisionFacialPhotoPolicyResult(
                version: self::VERSION,
                decision: FacialPhotoValidationDecision::Rejected,
                issues: $issues,
            );
        }

        return new LocalVisionFacialPhotoPolicyResult(
            version: self::VERSION,
            decision: FacialPhotoValidationDecision::Approved,
            issues: [],
        );
    }

    /**
     * @param  array<string, bool|int|float|string|null>  $metrics
     */
    private function ratioMetric(
        array $metrics,
        string $key
    ): ?float {
        $value = $metrics[$key] ?? null;

        if (! is_int($value) && ! is_float($value)) {
            return null;
        }

        $normalized = (float) $value;

        if (
            ! is_finite($normalized)
            || $normalized < 0
            || $normalized > 1
        ) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param  array<string, bool|int|float|string|null>  $metrics
     */
    private function booleanMetric(
        array $metrics,
        string $key
    ): ?bool {
        if (
            ! array_key_exists($key, $metrics)
            || ! is_bool($metrics[$key])
        ) {
            return null;
        }

        return $metrics[$key];
    }

    private function rejected(
        FacialPhotoValidationIssue $issue
    ): LocalVisionFacialPhotoPolicyResult {
        return new LocalVisionFacialPhotoPolicyResult(
            version: self::VERSION,
            decision: FacialPhotoValidationDecision::Rejected,
            issues: [$issue],
        );
    }

    private function inconclusive(
        FacialPhotoValidationIssue $issue
    ): LocalVisionFacialPhotoPolicyResult {
        return new LocalVisionFacialPhotoPolicyResult(
            version: self::VERSION,
            decision: FacialPhotoValidationDecision::Inconclusive,
            issues: [$issue],
        );
    }
}
