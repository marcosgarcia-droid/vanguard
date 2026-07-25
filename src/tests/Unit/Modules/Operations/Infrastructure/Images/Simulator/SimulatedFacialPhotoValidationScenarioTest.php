<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\Simulator;

use App\Modules\Operations\Infrastructure\Images\Simulator\SimulatedFacialPhotoValidationScenario;
use PHPUnit\Framework\TestCase;

final class SimulatedFacialPhotoValidationScenarioTest extends TestCase
{
    public function test_it_exposes_only_the_controlled_synthetic_scenarios(): void
    {
        $this->assertSame(
            [
                'approved',
                'no_face_detected',
                'multiple_faces_detected',
                'invalid_framing',
                'face_occluded',
                'validator_unavailable',
                'invalid_validator_response',
            ],
            array_map(
                static fn (
                    SimulatedFacialPhotoValidationScenario $scenario
                ): string => $scenario->value,
                SimulatedFacialPhotoValidationScenario::cases()
            )
        );
    }
}
