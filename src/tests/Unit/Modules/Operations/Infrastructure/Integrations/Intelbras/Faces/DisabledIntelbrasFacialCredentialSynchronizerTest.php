<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\DisabledIntelbrasFacialCredentialSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityProfile;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialDeviceFamily;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialItem;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialPlan;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizationStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialPhotoDescriptor;
use Tests\TestCase;

final class DisabledIntelbrasFacialCredentialSynchronizerTest extends TestCase
{
    public function test_it_implements_the_synchronizer_contract(): void
    {
        $synchronizer =
            new DisabledIntelbrasFacialCredentialSynchronizer;

        $this->assertInstanceOf(
            IntelbrasFacialCredentialSynchronizer::class,
            $synchronizer
        );
    }

    public function test_it_always_blocks_without_transport(): void
    {
        $result = (
            new DisabledIntelbrasFacialCredentialSynchronizer
        )->synchronize(
            $this->plan()
        );

        $this->assertSame(
            IntelbrasFacialCredentialSynchronizationStatus::Blocked,
            $result->status
        );

        $this->assertFalse($result->transportAttempted);
        $this->assertNull($result->planFingerprint);
        $this->assertNull($result->response);
    }

    private function plan(): IntelbrasFacialCredentialPlan
    {
        return new IntelbrasFacialCredentialPlan(
            compatibility: new IntelbrasFacialCredentialCompatibilityProfile(
                family: IntelbrasFacialCredentialDeviceFamily::BatchCapable,
                model: 'SYNTHETIC-DISABLED',
                firmware: 'SYNTHETIC-2026.04',
                maxItems: 10,
                supportsReplacement: true,
                requiresDisplayName: false,
            ),
            operation: IntelbrasFacialCredentialOperation::Register,
            items: [
                new IntelbrasFacialCredentialItem(
                    externalUserId: 'synthetic-disabled-001',
                    photo: new IntelbrasFacialPhotoDescriptor(
                        sha256: str_repeat('b', 64),
                        byteLength: 50_000,
                        width: 500,
                        height: 500,
                    ),
                ),
            ],
        );
    }
}
