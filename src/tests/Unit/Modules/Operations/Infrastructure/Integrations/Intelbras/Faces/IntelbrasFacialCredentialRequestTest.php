<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialItem;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialRequest;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialTransport;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialPhotoDescriptor;
use InvalidArgumentException;
use Tests\TestCase;

final class IntelbrasFacialCredentialRequestTest extends TestCase
{
    public function test_it_serializes_an_access_face_insert_batch(): void
    {
        $first = $this->item(
            userId: 'visitor-001',
            marker: 'A'
        );

        $second = $this->item(
            userId: 'visitor-002',
            marker: 'B'
        );

        $request = new IntelbrasFacialCredentialRequest(
            transport: IntelbrasFacialCredentialTransport::AccessFaceBatch,
            operation: IntelbrasFacialCredentialOperation::Insert,
            items: [$first, $second],
        );

        $this->assertSame(
            '/cgi-bin/AccessFace.cgi',
            $request->endpointPath()
        );

        $this->assertSame(
            'insertMulti',
            $request->action()
        );

        $this->assertSame(
            [
                'FaceList' => [
                    [
                        'UserID' => 'visitor-001',
                        'PhotoData' => [
                            $first->photo->transportBase64(),
                        ],
                    ],
                    [
                        'UserID' => 'visitor-002',
                        'PhotoData' => [
                            $second->photo->transportBase64(),
                        ],
                    ],
                ],
            ],
            $request->toIntelbrasPayload()
        );

        $this->assertSame(
            $request->payloadFingerprint(),
            hash(
                'sha256',
                $request->toDeterministicJson()
            )
        );
    }

    public function test_it_serializes_an_access_face_update(): void
    {
        $request = new IntelbrasFacialCredentialRequest(
            transport: IntelbrasFacialCredentialTransport::AccessFaceBatch,
            operation: IntelbrasFacialCredentialOperation::Update,
            items: [
                $this->item(
                    userId: 'visitor-003',
                    marker: 'C'
                ),
            ],
        );

        $this->assertSame(
            'updateMulti',
            $request->action()
        );
    }

    public function test_it_serializes_a_face_info_manager_add(): void
    {
        $item = $this->item(
            userId: 'visitor-004',
            marker: 'D',
            displayName: 'VISITANTE SINTÉTICO',
        );

        $request = new IntelbrasFacialCredentialRequest(
            transport: IntelbrasFacialCredentialTransport::FaceInfoManagerSingle,
            operation: IntelbrasFacialCredentialOperation::Insert,
            items: [$item],
        );

        $this->assertSame(
            '/cgi-bin/FaceInfoManager.cgi',
            $request->endpointPath()
        );

        $this->assertSame(
            'add',
            $request->action()
        );

        $this->assertSame(
            [
                'UserID' => 'visitor-004',
                'Info' => [
                    'UserName' => 'VISITANTE SINTÉTICO',
                    'PhotoData' => [
                        $item->photo->transportBase64(),
                    ],
                ],
            ],
            $request->toIntelbrasPayload()
        );
    }

    public function test_it_rejects_more_than_ten_access_face_items(): void
    {
        $items = [];

        for ($index = 1; $index <= 11; $index++) {
            $items[] = $this->item(
                userId: sprintf(
                    'visitor-%03d',
                    $index
                ),
                marker: 'E'
            );
        }

        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFacialCredentialRequest(
            transport: IntelbrasFacialCredentialTransport::AccessFaceBatch,
            operation: IntelbrasFacialCredentialOperation::Insert,
            items: $items,
        );
    }

    public function test_face_info_manager_requires_a_display_name(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFacialCredentialRequest(
            transport: IntelbrasFacialCredentialTransport::FaceInfoManagerSingle,
            operation: IntelbrasFacialCredentialOperation::Insert,
            items: [
                $this->item(
                    userId: 'visitor-005',
                    marker: 'F'
                ),
            ],
        );
    }

    public function test_face_info_manager_does_not_support_update(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFacialCredentialRequest(
            transport: IntelbrasFacialCredentialTransport::FaceInfoManagerSingle,
            operation: IntelbrasFacialCredentialOperation::Update,
            items: [
                $this->item(
                    userId: 'visitor-006',
                    marker: 'G',
                    displayName: 'VISITANTE',
                ),
            ],
        );
    }

    public function test_safe_result_never_exposes_base64(): void
    {
        $request = new IntelbrasFacialCredentialRequest(
            transport: IntelbrasFacialCredentialTransport::AccessFaceBatch,
            operation: IntelbrasFacialCredentialOperation::Insert,
            items: [
                $this->item(
                    userId: 'visitor-007',
                    marker: 'H'
                ),
            ],
        );

        $serialized = json_encode(
            $request->toSafeArray(),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            'PhotoData',
            $serialized
        );

        $this->assertStringNotContainsString(
            $request->items[0]->photo->transportBase64(),
            $serialized
        );
    }

    private function item(
        string $userId,
        string $marker,
        ?string $displayName = null,
    ): IntelbrasFacialCredentialItem {
        $bytes = "\xFF\xD8"
            .str_repeat($marker, 1_020)
            ."\xFF\xD9";

        return new IntelbrasFacialCredentialItem(
            externalUserId: $userId,
            photo: new IntelbrasFacialPhotoDescriptor(
                base64: base64_encode($bytes),
                byteLength: strlen($bytes),
                width: 500,
                height: 500,
            ),
            displayName: $displayName,
        );
    }
}
