<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\DocumentedIntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityResolutionStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use Tests\TestCase;

final class DocumentedIntelbrasFacialCredentialCompatibilityCatalogTest extends TestCase
{
    public function test_it_recognizes_ss_3532_mf_without_releasing_compatibility(): void
    {
        $resolution =
            new DocumentedIntelbrasFacialCredentialCompatibilityCatalog()
                ->resolve(
                    model: 'ss-3532_mf',
                    firmware: '20260416',
                );

        $this->assertSame(
            'SS 3532 MF',
            $resolution->model?->value
        );

        $this->assertSame(
            '20260416',
            $resolution->firmware?->value
        );

        $this->assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::UnverifiedCombination,
            $resolution->status
        );

        $this->assertFalse(
            $resolution->isCompatible()
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

    public function test_it_does_not_infer_the_wireless_model(): void
    {
        $resolution =
            new DocumentedIntelbrasFacialCredentialCompatibilityCatalog()
                ->resolve(
                    model: 'SS 3532 MF W',
                    firmware: '20260416',
                );

        $this->assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::UnknownModel,
            $resolution->status
        );

        $this->assertNull(
            $resolution->profile
        );
    }

    public function test_it_requires_an_explicit_firmware(): void
    {
        $resolution =
            new DocumentedIntelbrasFacialCredentialCompatibilityCatalog()
                ->resolve(
                    model: 'SS 3532 MF',
                    firmware: null,
                );

        $this->assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::MissingFirmware,
            $resolution->status
        );

        $this->assertNull(
            $resolution->profile
        );
    }
}
