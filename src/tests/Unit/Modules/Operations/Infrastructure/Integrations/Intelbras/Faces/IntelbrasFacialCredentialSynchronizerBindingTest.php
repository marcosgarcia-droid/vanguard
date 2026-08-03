<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\ConfiguredIntelbrasFacialCredentialSynchronizerResolver;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\DisabledIntelbrasFacialCredentialSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizerResolver;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\SimulatedIntelbrasFacialCredentialSynchronizer;
use Tests\TestCase;

final class IntelbrasFacialCredentialSynchronizerBindingTest extends TestCase
{
    public function test_it_binds_the_configured_resolver(): void
    {
        $resolver = $this->app->make(
            IntelbrasFacialCredentialSynchronizerResolver::class
        );

        $this->assertInstanceOf(
            ConfiguredIntelbrasFacialCredentialSynchronizerResolver::class,
            $resolver
        );
    }

    public function test_the_synchronizer_is_disabled_by_default(): void
    {
        $synchronizer = $this->app->make(
            IntelbrasFacialCredentialSynchronizer::class
        );

        $this->assertInstanceOf(
            DisabledIntelbrasFacialCredentialSynchronizer::class,
            $synchronizer
        );
    }

    public function test_it_resolves_the_explicit_simulator_in_testing(): void
    {
        config()->set(
            'intelbras_facial_synchronization.provider',
            'simulator'
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.enabled',
            true
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.scenario',
            'succeeded'
        );

        $synchronizer = $this->app->make(
            IntelbrasFacialCredentialSynchronizer::class
        );

        $this->assertInstanceOf(
            SimulatedIntelbrasFacialCredentialSynchronizer::class,
            $synchronizer
        );
    }

    public function test_invalid_configuration_fails_closed(): void
    {
        config()->set(
            'intelbras_facial_synchronization.provider',
            'intelbras'
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.enabled',
            true
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.scenario',
            'succeeded'
        );

        $synchronizer = $this->app->make(
            IntelbrasFacialCredentialSynchronizer::class
        );

        $this->assertInstanceOf(
            DisabledIntelbrasFacialCredentialSynchronizer::class,
            $synchronizer
        );
    }

    public function test_bindings_are_transient_and_read_current_configuration(): void
    {
        $first = $this->app->make(
            IntelbrasFacialCredentialSynchronizer::class
        );

        config()->set(
            'intelbras_facial_synchronization.provider',
            'simulator'
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.enabled',
            true
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.scenario',
            'duplicate_photo'
        );

        $second = $this->app->make(
            IntelbrasFacialCredentialSynchronizer::class
        );

        $third = $this->app->make(
            IntelbrasFacialCredentialSynchronizer::class
        );

        $this->assertInstanceOf(
            DisabledIntelbrasFacialCredentialSynchronizer::class,
            $first
        );

        $this->assertInstanceOf(
            SimulatedIntelbrasFacialCredentialSynchronizer::class,
            $second
        );

        $this->assertInstanceOf(
            SimulatedIntelbrasFacialCredentialSynchronizer::class,
            $third
        );

        $this->assertNotSame($second, $third);
    }
}
