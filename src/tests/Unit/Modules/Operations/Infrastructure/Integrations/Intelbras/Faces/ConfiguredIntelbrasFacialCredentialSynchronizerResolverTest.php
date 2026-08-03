<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\ConfiguredIntelbrasFacialCredentialSynchronizerResolver;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\DisabledIntelbrasFacialCredentialSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\SimulatedIntelbrasFacialCredentialSynchronizer;
use Tests\TestCase;

final class ConfiguredIntelbrasFacialCredentialSynchronizerResolverTest extends TestCase
{
    public function test_it_fails_closed_without_a_provider(): void
    {
        $synchronizer = $this->resolver(
            provider: null
        )->resolve();

        $this->assertInstanceOf(
            DisabledIntelbrasFacialCredentialSynchronizer::class,
            $synchronizer
        );
    }

    public function test_it_blocks_disabled_unknown_and_real_providers(): void
    {
        foreach (
            [
                'disabled',
                'unknown',
                'intelbras',
            ] as $provider
        ) {
            $synchronizer = $this->resolver(
                provider: $provider,
                simulatorEnabled: true,
                scenario: 'succeeded',
            )->resolve();

            $this->assertInstanceOf(
                DisabledIntelbrasFacialCredentialSynchronizer::class,
                $synchronizer
            );
        }
    }

    public function test_it_blocks_the_simulator_while_disabled(): void
    {
        $synchronizer = $this->resolver(
            provider: 'simulator',
            simulatorEnabled: false,
            scenario: 'succeeded',
        )->resolve();

        $this->assertInstanceOf(
            DisabledIntelbrasFacialCredentialSynchronizer::class,
            $synchronizer
        );
    }

    public function test_it_blocks_the_simulator_outside_allowed_environments(): void
    {
        $synchronizer = $this->resolver(
            environment: 'production',
            provider: 'simulator',
            simulatorEnabled: true,
            scenario: 'succeeded',
        )->resolve();

        $this->assertInstanceOf(
            DisabledIntelbrasFacialCredentialSynchronizer::class,
            $synchronizer
        );
    }

    public function test_it_requires_an_explicit_known_scenario(): void
    {
        foreach (
            [
                null,
                '',
                'unknown',
            ] as $scenario
        ) {
            $synchronizer = $this->resolver(
                provider: 'simulator',
                simulatorEnabled: true,
                scenario: $scenario,
            )->resolve();

            $this->assertInstanceOf(
                DisabledIntelbrasFacialCredentialSynchronizer::class,
                $synchronizer
            );
        }
    }

    public function test_it_resolves_the_simulator_only_in_allowed_environments(): void
    {
        foreach (
            [
                'local',
                'testing',
            ] as $environment
        ) {
            $synchronizer = $this->resolver(
                environment: $environment,
                provider: 'simulator',
                simulatorEnabled: true,
                scenario: 'succeeded',
            )->resolve();

            $this->assertInstanceOf(
                SimulatedIntelbrasFacialCredentialSynchronizer::class,
                $synchronizer
            );
        }
    }

    public function test_it_normalizes_safe_configuration_values(): void
    {
        $synchronizer = $this->resolver(
            environment: ' TESTING ',
            provider: ' SIMULATOR ',
            simulatorEnabled: true,
            scenario: ' SUCCEEDED ',
            allowedEnvironments: [
                ' LOCAL ',
                ' TESTING ',
            ],
        )->resolve();

        $this->assertInstanceOf(
            SimulatedIntelbrasFacialCredentialSynchronizer::class,
            $synchronizer
        );
    }

    /**
     * @param  array<array-key, mixed>  $allowedEnvironments
     */
    private function resolver(
        string $environment = 'testing',
        ?string $provider = 'disabled',
        bool $simulatorEnabled = false,
        ?string $scenario = null,
        array $allowedEnvironments = [
            'local',
            'testing',
        ],
    ): ConfiguredIntelbrasFacialCredentialSynchronizerResolver {
        return new ConfiguredIntelbrasFacialCredentialSynchronizerResolver(
            environment: $environment,
            provider: $provider,
            simulatorEnabled: $simulatorEnabled,
            simulatorAllowedEnvironments: $allowedEnvironments,
            simulatorScenario: $scenario,
        );
    }
}
