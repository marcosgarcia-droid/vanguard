<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialItem;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialRequest;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialResponseStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizationResult;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizationStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialTransport;
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

    public function test_it_generates_a_deterministic_fingerprint(): void
    {
        $first = $this->synchronize(
            SimulatedIntelbrasFacialCredentialSynchronizationScenario::Succeeded
        );

        $second = $this->synchronize(
            SimulatedIntelbrasFacialCredentialSynchronizationScenario::Succeeded
        );

        $this->assertSame(
            $first->requestFingerprint,
            $second->requestFingerprint
        );
    }

    public function test_safe_result_never_exposes_payload_or_person(): void
    {
        $request = $this->request();

        $result = (
            new SimulatedIntelbrasFacialCredentialSynchronizer(
                SimulatedIntelbrasFacialCredentialSynchronizationScenario::DuplicatePhoto
            )
        )->synchronize($request);

        $safeJson = json_encode(
            $result->toSafeArray(),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            'PhotoData',
            $safeJson
        );

        $this->assertStringNotContainsString(
            $request->items[0]->photo->transportBase64(),
            $safeJson
        );

        $this->assertStringNotContainsString(
            $request->items[0]->externalUserId,
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
            $this->request()
        );
    }

    private function request(): IntelbrasFacialCredentialRequest
    {
        $bytes = "\xFF\xD8"
            .str_repeat('C', 1_020)
            ."\xFF\xD9";

        return new IntelbrasFacialCredentialRequest(
            transport: IntelbrasFacialCredentialTransport::AccessFaceBatch,
            operation: IntelbrasFacialCredentialOperation::Insert,
            items: [
                new IntelbrasFacialCredentialItem(
                    externalUserId: 'synthetic-visitor-003',
                    photo: new IntelbrasFacialPhotoDescriptor(
                        base64: base64_encode($bytes),
                        byteLength: strlen($bytes),
                        width: 500,
                        height: 500,
                    ),
                ),
            ],
        );
    }
}
