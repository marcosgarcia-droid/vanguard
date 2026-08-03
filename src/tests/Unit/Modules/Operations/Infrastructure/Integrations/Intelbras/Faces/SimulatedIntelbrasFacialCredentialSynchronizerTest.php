<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityProfile;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialDeviceFamily;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialItem;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialPlan;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialResponseStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizationResult;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizationStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialPhotoDescriptor;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\SimulatedIntelbrasFacialCredentialSynchronizationScenario;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\SimulatedIntelbrasFacialCredentialSynchronizer;
use Tests\TestCase;

final class SimulatedIntelbrasFacialCredentialSynchronizerTest extends TestCase
{
    public function test_it_implements_the_synchronizer_contract(): void
    {
        $synchronizer =
            new SimulatedIntelbrasFacialCredentialSynchronizer(
                SimulatedIntelbrasFacialCredentialSynchronizationScenario::Succeeded
            );

        $this->assertInstanceOf(
            IntelbrasFacialCredentialSynchronizer::class,
            $synchronizer
        );
    }

    public function test_it_simulates_a_success_without_transport(): void
    {
        $result = $this->synchronize(
            SimulatedIntelbrasFacialCredentialSynchronizationScenario::Succeeded
        );

        $this->assertSame(
            IntelbrasFacialCredentialSynchronizationStatus::Simulated,
            $result->status
        );

        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::Succeeded,
            $result->response?->status
        );

        $this->assertTrue(
            $result->wasSimulatedSuccessfully()
        );

        $this->assertFalse($result->transportAttempted);
    }

    public function test_it_simulates_a_duplicate_photo(): void
    {
        $result = $this->synchronize(
            SimulatedIntelbrasFacialCredentialSynchronizationScenario::DuplicatePhoto
        );

        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::DuplicatePhoto,
            $result->response?->status
        );

        $this->assertTrue($result->isDuplicatePhoto());
        $this->assertTrue($result->requiresAttention());
        $this->assertFalse($result->transportAttempted);
    }

    public function test_it_simulates_a_generic_failure(): void
    {
        $result = $this->synchronize(
            SimulatedIntelbrasFacialCredentialSynchronizationScenario::Failed
        );

        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::Failed,
            $result->response?->status
        );

        $this->assertSame(
            999_999,
            $result->response?->code
        );

        $this->assertTrue($result->requiresAttention());
        $this->assertFalse($result->transportAttempted);
    }

    public function test_it_simulates_an_invalid_response_fail_closed(): void
    {
        $result = $this->synchronize(
            SimulatedIntelbrasFacialCredentialSynchronizationScenario::InvalidResponse
        );

        $this->assertSame(
            IntelbrasFacialCredentialResponseStatus::InvalidResponse,
            $result->response?->status
        );

        $this->assertTrue($result->requiresAttention());
        $this->assertFalse($result->transportAttempted);
    }

    public function test_it_generates_a_deterministic_plan_fingerprint(): void
    {
        $first = $this->synchronize(
            SimulatedIntelbrasFacialCredentialSynchronizationScenario::Succeeded
        );

        $second = $this->synchronize(
            SimulatedIntelbrasFacialCredentialSynchronizationScenario::Succeeded
        );

        $this->assertSame(
            $first->planFingerprint,
            $second->planFingerprint
        );
    }

    public function test_safe_result_never_exposes_person_or_photo_metadata(): void
    {
        $result = $this->synchronize(
            SimulatedIntelbrasFacialCredentialSynchronizationScenario::DuplicatePhoto
        );

        $safeJson = json_encode(
            $result->toSafeArray(),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            'synthetic-simulator-001',
            $safeJson
        );

        $this->assertStringNotContainsString(
            str_repeat('c', 64),
            $safeJson
        );

        $this->assertStringNotContainsString(
            'Synthetic batch failure',
            $safeJson
        );
    }

    private function synchronize(
        SimulatedIntelbrasFacialCredentialSynchronizationScenario $scenario
    ): IntelbrasFacialCredentialSynchronizationResult {
        return (
            new SimulatedIntelbrasFacialCredentialSynchronizer(
                $scenario
            )
        )->synchronize(
            $this->plan()
        );
    }

    private function plan(): IntelbrasFacialCredentialPlan
    {
        return new IntelbrasFacialCredentialPlan(
            compatibility: new IntelbrasFacialCredentialCompatibilityProfile(
                family: IntelbrasFacialCredentialDeviceFamily::BatchCapable,
                model: 'SYNTHETIC-SIMULATOR',
                firmware: 'SYNTHETIC-2026.04',
                maxItems: 10,
                supportsReplacement: true,
                requiresDisplayName: false,
            ),
            operation: IntelbrasFacialCredentialOperation::Register,
            items: [
                new IntelbrasFacialCredentialItem(
                    externalUserId: 'synthetic-simulator-001',
                    photo: new IntelbrasFacialPhotoDescriptor(
                        sha256: str_repeat('c', 64),
                        byteLength: 50_000,
                        width: 500,
                        height: 500,
                    ),
                ),
            ],
        );
    }
}
