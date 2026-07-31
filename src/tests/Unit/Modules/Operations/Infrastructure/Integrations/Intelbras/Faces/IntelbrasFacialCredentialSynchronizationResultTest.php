<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialItem;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialRequest;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialResponse;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizationResult;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizationStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialTransport;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialPhotoDescriptor;
use Tests\TestCase;

final class IntelbrasFacialCredentialSynchronizationResultTest extends TestCase
{
    public function test_it_represents_a_fail_closed_blocked_result(): void
    {
        $request = $this->request();

        $result =
            IntelbrasFacialCredentialSynchronizationResult::blocked(
                $request
            );

        $this->assertSame(
            IntelbrasFacialCredentialSynchronizationStatus::Blocked,
            $result->status
        );

        $this->assertNull($result->requestFingerprint);
        $this->assertNull($result->response);
        $this->assertFalse($result->transportAttempted);
        $this->assertTrue($result->requiresAttention());

        $this->assertSame(
            [
                'status' => 'blocked',
                'transport' => 'access_face_batch',
                'operation' => 'insert',
                'item_count' => 1,
                'request_fingerprint' => null,
                'response' => null,
                'transport_attempted' => false,
                'message' => 'A sincronização facial está bloqueada e nenhum transporte foi executado.',
            ],
            $result->toSafeArray()
        );
    }

    public function test_it_represents_a_successful_simulation(): void
    {
        $request = $this->request();

        $result =
            IntelbrasFacialCredentialSynchronizationResult::simulated(
                request: $request,
                response: IntelbrasFacialCredentialResponse::succeeded(),
            );

        $this->assertSame(
            IntelbrasFacialCredentialSynchronizationStatus::Simulated,
            $result->status
        );

        $this->assertSame(
            $request->payloadFingerprint(),
            $result->requestFingerprint
        );

        $this->assertTrue(
            $result->wasSimulatedSuccessfully()
        );

        $this->assertFalse($result->requiresAttention());
        $this->assertFalse($result->transportAttempted);
    }

    public function test_duplicate_photo_requires_attention(): void
    {
        $result =
            IntelbrasFacialCredentialSynchronizationResult::simulated(
                request: $this->request(),
                response: IntelbrasFacialCredentialResponse::duplicatePhoto(),
            );

        $this->assertTrue($result->isDuplicatePhoto());
        $this->assertTrue($result->requiresAttention());
        $this->assertFalse(
            $result->wasSimulatedSuccessfully()
        );
    }

    private function request(): IntelbrasFacialCredentialRequest
    {
        $bytes = "\xFF\xD8"
            .str_repeat('A', 1_020)
            ."\xFF\xD9";

        return new IntelbrasFacialCredentialRequest(
            transport: IntelbrasFacialCredentialTransport::AccessFaceBatch,
            operation: IntelbrasFacialCredentialOperation::Insert,
            items: [
                new IntelbrasFacialCredentialItem(
                    externalUserId: 'synthetic-visitor-001',
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
