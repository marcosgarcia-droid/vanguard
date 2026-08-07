<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\ConfiguredIntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityResolutionStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\SimulatedIntelbrasFacialCredentialCompatibilityCatalog;
use Tests\TestCase;

final class IntelbrasFacialCredentialCompatibilityCatalogBindingTest extends TestCase
{
    public function test_container_resolves_the_configured_catalog(): void
    {
        self::assertInstanceOf(
            ConfiguredIntelbrasFacialCredentialCompatibilityCatalog::class,
            app(
                IntelbrasFacialCredentialCompatibilityCatalog::class
            )
        );
    }

    public function test_binding_reads_the_current_safe_configuration(): void
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
            'intelbras_facial_synchronization.simulator.allowed_environments',
            ['testing']
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.scenario',
            'succeeded'
        );

        $enabled = app(
            IntelbrasFacialCredentialCompatibilityCatalog::class
        )->resolve(
            model: SimulatedIntelbrasFacialCredentialCompatibilityCatalog::MODEL,

            firmware: SimulatedIntelbrasFacialCredentialCompatibilityCatalog::FIRMWARE,
        );

        self::assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::Compatible,
            $enabled->status
        );

        config()->set(
            'intelbras_facial_synchronization.provider',
            'disabled'
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.enabled',
            false
        );

        $disabled = app(
            IntelbrasFacialCredentialCompatibilityCatalog::class
        )->resolve(
            model: SimulatedIntelbrasFacialCredentialCompatibilityCatalog::MODEL,

            firmware: SimulatedIntelbrasFacialCredentialCompatibilityCatalog::FIRMWARE,
        );

        self::assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::UnknownModel,
            $disabled->status
        );
    }

    public function test_physical_compatibility_remains_unreleased(): void
    {
        config()->set(
            'intelbras_facial_synchronization.provider',
            'disabled'
        );

        $resolution = app(
            IntelbrasFacialCredentialCompatibilityCatalog::class
        )->resolve(
            model: 'SS 3532 MF',
            firmware: '20260416',
        );

        self::assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::UnverifiedCombination,
            $resolution->status
        );

        self::assertFalse(
            $resolution->isCompatible()
        );
    }
}
