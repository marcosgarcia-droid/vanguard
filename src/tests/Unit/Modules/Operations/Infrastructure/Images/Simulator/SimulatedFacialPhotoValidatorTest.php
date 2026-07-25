<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\Simulator;

use App\Modules\Operations\Application\FacialPhotos\Validation\ValidateFacialPhotoUseCase;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use App\Modules\Operations\Infrastructure\Images\Simulator\SimulatedFacialPhotoValidationScenario;
use App\Modules\Operations\Infrastructure\Images\Simulator\SimulatedFacialPhotoValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SimulatedFacialPhotoValidatorTest extends TestCase
{
    public function test_it_returns_deterministic_results_for_every_scenario(): void
    {
        $expectations = [
            [
                SimulatedFacialPhotoValidationScenario::Approved,
                FacialPhotoValidationDecision::Approved,
                1,
                [],
            ],
            [
                SimulatedFacialPhotoValidationScenario::NoFaceDetected,
                FacialPhotoValidationDecision::Rejected,
                0,
                ['no_face_detected'],
            ],
            [
                SimulatedFacialPhotoValidationScenario::MultipleFacesDetected,
                FacialPhotoValidationDecision::Rejected,
                2,
                ['multiple_faces_detected'],
            ],
            [
                SimulatedFacialPhotoValidationScenario::InvalidFraming,
                FacialPhotoValidationDecision::Rejected,
                1,
                [
                    'face_not_centered',
                    'head_pose_invalid',
                ],
            ],
            [
                SimulatedFacialPhotoValidationScenario::FaceOccluded,
                FacialPhotoValidationDecision::Rejected,
                1,
                ['face_occluded'],
            ],
            [
                SimulatedFacialPhotoValidationScenario::ValidatorUnavailable,
                FacialPhotoValidationDecision::Inconclusive,
                0,
                ['validator_unavailable'],
            ],
            [
                SimulatedFacialPhotoValidationScenario::InvalidValidatorResponse,
                FacialPhotoValidationDecision::Inconclusive,
                0,
                ['invalid_validator_response'],
            ],
        ];

        foreach (
            $expectations as [
                $scenario,
                $decision,
                $faceCount,
                $issueCodes,
            ]
        ) {
            $result = $this->validate(
                $scenario
            );

            $this->assertSame(
                SimulatedFacialPhotoValidator::VALIDATOR,
                $result->validator
            );

            $this->assertSame(
                SimulatedFacialPhotoValidator::VERSION,
                $result->version
            );

            $this->assertSame(
                $decision,
                $result->decision
            );

            $this->assertSame(
                $faceCount,
                $result->faceCount
            );

            $this->assertSame(
                $scenario->value,
                $result->metrics['scenario']
            );

            $this->assertSame(
                $issueCodes,
                $result->issueCodes()
            );
        }
    }

    public function test_it_does_not_require_a_real_image_file(): void
    {
        $path =
            '/tmp/vanguard-synthetic-photo-does-not-exist.jpg';

        $this->assertFileDoesNotExist(
            $path
        );

        $result = $this->validate(
            SimulatedFacialPhotoValidationScenario::Approved,
            $path
        );

        $this->assertTrue(
            $result->isApproved()
        );

        $this->assertSame(
            1,
            $result->faceCount
        );
    }

    public function test_it_reports_validator_failures_as_inconclusive(): void
    {
        foreach (
            [
                SimulatedFacialPhotoValidationScenario::ValidatorUnavailable,
                SimulatedFacialPhotoValidationScenario::InvalidValidatorResponse,
            ] as $scenario
        ) {
            $result = $this->validate(
                $scenario
            );

            $this->assertTrue(
                $result->isInconclusive()
            );

            $this->assertFalse(
                $result->isRejected()
            );
        }
    }

    public function test_it_rejects_empty_or_relative_paths(): void
    {
        foreach (
            [
                '',
                '   ',
                'relative-photo.jpg',
            ] as $path
        ) {
            try {
                $this->validate(
                    SimulatedFacialPhotoValidationScenario::Approved,
                    $path
                );

                $this->fail(
                    'Era esperada uma InvalidArgumentException.'
                );
            } catch (
                InvalidArgumentException $exception
            ) {
                $this->assertSame(
                    'O validador facial exige um caminho absoluto.',
                    $exception->getMessage()
                );
            }
        }
    }

    private function validate(
        SimulatedFacialPhotoValidationScenario $scenario,
        string $path = '/tmp/vanguard-synthetic-photo.jpg',
    ): FacialPhotoValidationResult {
        $useCase = new ValidateFacialPhotoUseCase(
            new SimulatedFacialPhotoValidator(
                $scenario
            )
        );

        return $useCase->execute(
            $path
        );
    }
}
