<?php

namespace App\Modules\Operations\Infrastructure\Images\Simulator;

use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use InvalidArgumentException;

final readonly class SimulatedFacialPhotoValidator implements FacialPhotoValidator
{
    public const VALIDATOR = 'simulated-facial-validator';

    public const VERSION = 'synthetic-v1';

    public function __construct(
        private SimulatedFacialPhotoValidationScenario $scenario,
    ) {}

    public function validate(
        string $absolutePath
    ): FacialPhotoValidationResult {
        $this->assertAbsolutePath(
            $absolutePath
        );

        return match ($this->scenario) {
            SimulatedFacialPhotoValidationScenario::Approved => $this->approved(),

            SimulatedFacialPhotoValidationScenario::NoFaceDetected => $this->noFaceDetected(),

            SimulatedFacialPhotoValidationScenario::MultipleFacesDetected => $this->multipleFacesDetected(),

            SimulatedFacialPhotoValidationScenario::InvalidFraming => $this->invalidFraming(),

            SimulatedFacialPhotoValidationScenario::FaceOccluded => $this->faceOccluded(),

            SimulatedFacialPhotoValidationScenario::ValidatorUnavailable => $this->validatorUnavailable(),

            SimulatedFacialPhotoValidationScenario::InvalidValidatorResponse => $this->invalidValidatorResponse(),
        };
    }

    private function approved(): FacialPhotoValidationResult
    {
        return $this->result(
            decision: FacialPhotoValidationDecision::Approved,
            faceCount: 1,
            metrics: [
                'confidence' => 0.99,
                'centered' => true,
                'face_ratio' => 0.48,
            ],
            issues: [],
        );
    }

    private function noFaceDetected(): FacialPhotoValidationResult
    {
        return $this->result(
            decision: FacialPhotoValidationDecision::Rejected,
            faceCount: 0,
            metrics: [
                'confidence' => 0.0,
            ],
            issues: [
                FacialPhotoValidationIssue::NoFaceDetected,
            ],
        );
    }

    private function multipleFacesDetected(): FacialPhotoValidationResult
    {
        return $this->result(
            decision: FacialPhotoValidationDecision::Rejected,
            faceCount: 2,
            metrics: [
                'confidence' => 0.96,
            ],
            issues: [
                FacialPhotoValidationIssue::MultipleFacesDetected,
            ],
        );
    }

    private function invalidFraming(): FacialPhotoValidationResult
    {
        return $this->result(
            decision: FacialPhotoValidationDecision::Rejected,
            faceCount: 1,
            metrics: [
                'confidence' => 0.91,
                'centered' => false,
            ],
            issues: [
                FacialPhotoValidationIssue::FaceNotCentered,
                FacialPhotoValidationIssue::HeadPoseInvalid,
            ],
        );
    }

    private function faceOccluded(): FacialPhotoValidationResult
    {
        return $this->result(
            decision: FacialPhotoValidationDecision::Rejected,
            faceCount: 1,
            metrics: [
                'confidence' => 0.79,
            ],
            issues: [
                FacialPhotoValidationIssue::FaceOccluded,
            ],
        );
    }

    private function validatorUnavailable(): FacialPhotoValidationResult
    {
        return $this->result(
            decision: FacialPhotoValidationDecision::Inconclusive,
            faceCount: 0,
            metrics: [
                'available' => false,
            ],
            issues: [
                FacialPhotoValidationIssue::ValidatorUnavailable,
            ],
        );
    }

    private function invalidValidatorResponse(): FacialPhotoValidationResult
    {
        return $this->result(
            decision: FacialPhotoValidationDecision::Inconclusive,
            faceCount: 0,
            metrics: [
                'response_valid' => false,
            ],
            issues: [
                FacialPhotoValidationIssue::InvalidValidatorResponse,
            ],
        );
    }

    /**
     * @param  array<string, bool|int|float|string|null>  $metrics
     * @param  list<FacialPhotoValidationIssue>  $issues
     */
    private function result(
        FacialPhotoValidationDecision $decision,
        int $faceCount,
        array $metrics,
        array $issues,
    ): FacialPhotoValidationResult {
        return new FacialPhotoValidationResult(
            validator: self::VALIDATOR,
            version: self::VERSION,
            decision: $decision,
            faceCount: $faceCount,
            metrics: [
                'scenario' => $this->scenario->value,
                ...$metrics,
            ],
            issues: $issues,
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
                'O validador facial exige um caminho absoluto.'
            );
        }
    }
}
