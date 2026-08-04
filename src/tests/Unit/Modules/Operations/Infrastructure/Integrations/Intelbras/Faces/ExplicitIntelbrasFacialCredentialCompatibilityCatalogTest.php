<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\ExplicitIntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasDeviceModel;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityProfile;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityResolutionStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialDeviceFamily;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use Tests\TestCase;

final class ExplicitIntelbrasFacialCredentialCompatibilityCatalogTest extends TestCase
{
    public function test_it_resolves_only_an_exact_documented_profile(): void
    {
        $resolution = $this->catalog()->resolve(
            model: 'vanguard-synthetic_device',
            firmware: '20991231',
        );

        $this->assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::Compatible,
            $resolution->status
        );

        $this->assertTrue(
            $resolution->isCompatible()
        );

        $this->assertTrue(
            $resolution->supportsOperation(
                IntelbrasFacialCredentialOperation::Register
            )
        );

        $this->assertTrue(
            $resolution->supportsOperation(
                IntelbrasFacialCredentialOperation::Replace
            )
        );

        $this->assertSame(
            IntelbrasFacialCredentialDeviceFamily::BatchCapable,
            $resolution->profile?->family
        );
    }

    public function test_it_blocks_an_unverified_firmware_combination(): void
    {
        $resolution = $this->catalog()->resolve(
            model: 'VANGUARD SYNTHETIC DEVICE',
            firmware: '20991230',
        );

        $this->assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::UnverifiedCombination,
            $resolution->status
        );

        $this->assertNull(
            $resolution->profile
        );

        $this->assertFalse(
            $resolution->supportsOperation(
                IntelbrasFacialCredentialOperation::Register
            )
        );

        $this->assertFalse(
            $resolution->supportsOperation(
                IntelbrasFacialCredentialOperation::Replace
            )
        );
    }

    public function test_it_does_not_match_a_partial_model_name(): void
    {
        $resolution = $this->catalog()->resolve(
            model: 'VANGUARD SYNTHETIC',
            firmware: '20991231',
        );

        $this->assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::UnknownModel,
            $resolution->status
        );

        $this->assertNull(
            $resolution->profile
        );
    }

    public function test_it_distinguishes_missing_and_invalid_models(): void
    {
        $missing = $this->catalog()->resolve(
            model: null,
            firmware: '20991231',
        );

        $invalid = $this->catalog()->resolve(
            model: "VANGUARD \x01 DEVICE",
            firmware: '20991231',
        );

        $this->assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::MissingModel,
            $missing->status
        );

        $this->assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::InvalidModel,
            $invalid->status
        );
    }

    public function test_it_distinguishes_missing_and_invalid_firmware(): void
    {
        $missing = $this->catalog()->resolve(
            model: 'VANGUARD SYNTHETIC DEVICE',
            firmware: null,
        );

        $invalid = $this->catalog()->resolve(
            model: 'VANGUARD SYNTHETIC DEVICE',
            firmware: 'invalid-firmware',
        );

        $this->assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::MissingFirmware,
            $missing->status
        );

        $this->assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::InvalidFirmware,
            $invalid->status
        );
    }

    private function catalog(): ExplicitIntelbrasFacialCredentialCompatibilityCatalog
    {
        $model = new IntelbrasDeviceModel(
            'VANGUARD SYNTHETIC DEVICE'
        );

        return new ExplicitIntelbrasFacialCredentialCompatibilityCatalog(
            knownModels: [
                $model,
            ],
            documentedProfiles: [
                new IntelbrasFacialCredentialCompatibilityProfile(
                    family: IntelbrasFacialCredentialDeviceFamily::BatchCapable,
                    model: $model->value,
                    firmware: '20991231',
                    maxItems: 10,
                    supportsReplacement: true,
                    requiresDisplayName: false,
                ),
            ],
        );
    }
}
