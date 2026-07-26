<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\Resolution;

use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolutionException;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorSelection;
use App\Modules\Operations\Infrastructure\Images\Resolution\ConfiguredFacialPhotoValidatorResolver;
use App\Modules\Operations\Infrastructure\Images\Simulator\SimulatedFacialPhotoValidator;
use PHPUnit\Framework\TestCase;

final class ConfiguredFacialPhotoValidatorResolverTest extends TestCase
{
    public function test_it_resolves_the_simulator_explicitly_in_local_environment(): void
    {
        $resolver = new ConfiguredFacialPhotoValidatorResolver(
            environment: 'local',
            simulatorEnabled: true,
        );

        $validator = $resolver->resolve(
            FacialPhotoValidatorSelection::fromInput(
                provider: 'simulator',
                scenario: 'approved',
            )
        );

        $this->assertInstanceOf(
            SimulatedFacialPhotoValidator::class,
            $validator
        );

        $result = $validator->validate(
            '/tmp/vanguard-explicit-simulator-photo.jpg'
        );

        $this->assertTrue(
            $result->isApproved()
        );

        $this->assertSame(
            'approved',
            $result->metrics['scenario']
        );

        $this->assertSame(
            SimulatedFacialPhotoValidator::VALIDATOR,
            $result->validator
        );
    }

    public function test_it_allows_the_simulator_in_testing_environment(): void
    {
        $resolver = new ConfiguredFacialPhotoValidatorResolver(
            environment: 'testing',
            simulatorEnabled: true,
        );

        $validator = $resolver->resolve(
            FacialPhotoValidatorSelection::fromInput(
                provider: 'simulator',
                scenario: 'no_face_detected',
            )
        );

        $this->assertInstanceOf(
            SimulatedFacialPhotoValidator::class,
            $validator
        );
    }

    public function test_it_normalizes_the_environment_name(): void
    {
        $resolver = new ConfiguredFacialPhotoValidatorResolver(
            environment: '  LOCAL  ',
            simulatorEnabled: true,
        );

        $validator = $resolver->resolve(
            FacialPhotoValidatorSelection::fromInput(
                provider: 'simulator',
                scenario: 'approved',
            )
        );

        $this->assertInstanceOf(
            SimulatedFacialPhotoValidator::class,
            $validator
        );
    }

    public function test_it_blocks_the_simulator_when_disabled(): void
    {
        $resolver = new ConfiguredFacialPhotoValidatorResolver(
            environment: 'local',
            simulatorEnabled: false,
        );

        $this->expectException(
            FacialPhotoValidatorResolutionException::class
        );

        $this->expectExceptionMessage(
            'O provider de validação facial informado está desativado.'
        );

        $resolver->resolve(
            FacialPhotoValidatorSelection::fromInput(
                provider: 'simulator',
                scenario: 'approved',
            )
        );
    }

    public function test_it_blocks_the_simulator_outside_allowed_environments(): void
    {
        $resolver = new ConfiguredFacialPhotoValidatorResolver(
            environment: 'production',
            simulatorEnabled: true,
        );

        $this->expectException(
            FacialPhotoValidatorResolutionException::class
        );

        $this->expectExceptionMessage(
            'O provider de validação facial informado não é permitido neste ambiente.'
        );

        $resolver->resolve(
            FacialPhotoValidatorSelection::fromInput(
                provider: 'simulator',
                scenario: 'approved',
            )
        );
    }

    public function test_it_requires_an_explicit_simulator_scenario(): void
    {
        $resolver = new ConfiguredFacialPhotoValidatorResolver(
            environment: 'local',
            simulatorEnabled: true,
        );

        $this->expectException(
            FacialPhotoValidatorResolutionException::class
        );

        $this->expectExceptionMessage(
            'O cenário do simulador facial é obrigatório.'
        );

        $resolver->resolve(
            FacialPhotoValidatorSelection::fromInput(
                provider: 'simulator',
            )
        );
    }

    public function test_it_rejects_an_unknown_scenario_without_fallback(): void
    {
        $resolver = new ConfiguredFacialPhotoValidatorResolver(
            environment: 'local',
            simulatorEnabled: true,
        );

        $this->expectException(
            FacialPhotoValidatorResolutionException::class
        );

        $this->expectExceptionMessage(
            'O cenário informado para o simulador facial não é suportado.'
        );

        $resolver->resolve(
            FacialPhotoValidatorSelection::fromInput(
                provider: 'simulator',
                scenario: 'unknown-scenario',
            )
        );
    }
}
