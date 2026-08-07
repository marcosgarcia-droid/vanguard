<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityResolutionStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialDeviceFamily;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\SimulatedIntelbrasFacialCredentialCompatibilityCatalog;
use Tests\TestCase;

final class SimulatedIntelbrasFacialCredentialCompatibilityCatalogTest extends TestCase
{
    public function test_it_releases_only_the_explicit_synthetic_profile(): void
    {
        $catalog =
            new SimulatedIntelbrasFacialCredentialCompatibilityCatalog;

        $resolution = $catalog->resolve(
            model: SimulatedIntelbrasFacialCredentialCompatibilityCatalog::MODEL,

            firmware: SimulatedIntelbrasFacialCredentialCompatibilityCatalog::FIRMWARE,
        );

        self::assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::Compatible,
            $resolution->status
        );

        self::assertTrue(
            $resolution->isCompatible()
        );

        self::assertSame(
            IntelbrasFacialCredentialDeviceFamily::BatchCapable,
            $resolution->profile?->family
        );

        self::assertSame(
            10,
            $resolution->profile?->maxItems
        );

        self::assertTrue(
            $resolution->supportsOperation(
                IntelbrasFacialCredentialOperation::Register
            )
        );

        self::assertTrue(
            $resolution->supportsOperation(
                IntelbrasFacialCredentialOperation::Replace
            )
        );
    }

    public function test_it_does_not_release_a_physical_model(): void
    {
        $resolution =
            (new SimulatedIntelbrasFacialCredentialCompatibilityCatalog)
                ->resolve(
                    model: 'SS 3532 MF',
                    firmware: '20260416',
                );

        self::assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::UnknownModel,
            $resolution->status
        );

        self::assertFalse(
            $resolution->isCompatible()
        );
    }

    public function test_it_requires_the_exact_synthetic_firmware(): void
    {
        $resolution =
            (new SimulatedIntelbrasFacialCredentialCompatibilityCatalog)
                ->resolve(
                    model: SimulatedIntelbrasFacialCredentialCompatibilityCatalog::MODEL,

                    firmware: '20991230',
                );

        self::assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::UnverifiedCombination,
            $resolution->status
        );
    }
}
