<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\DisabledIntelbrasFacialCredentialSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialItem;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialRequest;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizationStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialTransport;
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
            $this->request()
        );

        $this->assertSame(
            IntelbrasFacialCredentialSynchronizationStatus::Blocked,
            $result->status
        );

        $this->assertFalse($result->transportAttempted);
        $this->assertNull($result->requestFingerprint);
        $this->assertNull($result->response);
    }

    private function request(): IntelbrasFacialCredentialRequest
    {
        $bytes = "\xFF\xD8"
            .str_repeat('B', 1_020)
            ."\xFF\xD9";

        return new IntelbrasFacialCredentialRequest(
            transport: IntelbrasFacialCredentialTransport::AccessFaceBatch,
            operation: IntelbrasFacialCredentialOperation::Insert,
            items: [
                new IntelbrasFacialCredentialItem(
                    externalUserId: 'synthetic-visitor-002',
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
