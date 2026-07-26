<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\Resolution;

use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorProvider;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolver;
use App\Modules\Operations\Infrastructure\Images\Simulator\SimulatedFacialPhotoValidationScenario;
use App\Modules\Operations\Infrastructure\Images\Simulator\SimulatedFacialPhotoValidator;
use Tests\TestCase;

final class FacialPhotoValidationConfigurationTest extends TestCase
{
    public function test_it_keeps_facial_validation_fail_closed_by_default(): void
    {
        $this->assertFalse(
            (bool) config(
                'facial_photos.validation.enabled'
            )
        );

        $this->assertSame(
            '',
            trim(
                (string) config(
                    'facial_photos.validation.provider'
                )
            )
        );

        $this->assertFalse(
            (bool) config(
                'facial_photos.validation.simulator.enabled'
            )
        );

        $this->assertSame(
            [
                'local',
                'testing',
            ],
            config(
                'facial_photos.validation.simulator.allowed_environments'
            )
        );

        $this->assertSame(
            'validator_unavailable',
            config(
                'facial_photos.validation.simulator.default_scenario'
            )
        );
    }

    public function test_the_default_simulator_scenario_is_inconclusive(): void
    {
        $scenario =
            SimulatedFacialPhotoValidationScenario::tryFrom(
                (string) config(
                    'facial_photos.validation.simulator.default_scenario'
                )
            );

        $this->assertInstanceOf(
            SimulatedFacialPhotoValidationScenario::class,
            $scenario
        );

        $validator =
            new SimulatedFacialPhotoValidator(
                $scenario
            );

        $result = $validator->validate(
            '/tmp/vanguard-safe-default-facial-photo.jpg'
        );

        $this->assertTrue(
            $result->isInconclusive()
        );

        $this->assertFalse(
            $result->isApproved()
        );

        $this->assertFalse(
            $result->isRejected()
        );

        $this->assertSame(
            [
                'validator_unavailable',
            ],
            $result->issueCodes()
        );
    }

    public function test_the_environment_example_documents_safe_defaults(): void
    {
        $environment = file_get_contents(
            base_path(
                '.env.example'
            )
        );

        $this->assertIsString(
            $environment
        );

        foreach (
            [
                'VANGUARD_FACIAL_PHOTO_VALIDATION_ENABLED=false',
                'VANGUARD_FACIAL_PHOTO_VALIDATION_PROVIDER=',
                'VANGUARD_FACIAL_PHOTO_VALIDATION_SIMULATOR_ENABLED=false',
                'VANGUARD_FACIAL_PHOTO_VALIDATION_SIMULATOR_DEFAULT_SCENARIO=validator_unavailable',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $environment
            );
        }
    }

    public function test_phpunit_forces_safe_validation_defaults(): void
    {
        $phpunit = file_get_contents(
            base_path(
                'phpunit.xml'
            )
        );

        $this->assertIsString(
            $phpunit
        );

        foreach (
            [
                'name="VANGUARD_FACIAL_PHOTO_VALIDATION_ENABLED" value="false"',
                'name="VANGUARD_FACIAL_PHOTO_VALIDATION_PROVIDER" value=""',
                'name="VANGUARD_FACIAL_PHOTO_VALIDATION_SIMULATOR_ENABLED" value="false"',
                'name="VANGUARD_FACIAL_PHOTO_VALIDATION_SIMULATOR_DEFAULT_SCENARIO" value="validator_unavailable"',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $phpunit
            );
        }
    }

    public function test_configuration_binds_only_the_resolver(): void
    {
        $this->assertFalse(
            $this->app->bound(
                FacialPhotoValidator::class
            )
        );

        $this->assertTrue(
            $this->app->bound(
                FacialPhotoValidatorResolver::class
            )
        );
    }

    public function test_no_provider_is_selected_implicitly(): void
    {
        $provider = trim(
            (string) config(
                'facial_photos.validation.provider'
            )
        );

        $this->assertNull(
            FacialPhotoValidatorProvider::tryFrom(
                $provider
            )
        );
    }
}
