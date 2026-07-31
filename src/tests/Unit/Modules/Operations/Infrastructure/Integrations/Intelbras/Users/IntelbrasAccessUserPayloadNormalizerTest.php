<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Users;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users\IntelbrasAccessUserPayload;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users\IntelbrasAccessUserPayloadNormalizer;
use InvalidArgumentException;
use Tests\TestCase;

final class IntelbrasAccessUserPayloadNormalizerTest extends TestCase
{
    public function test_it_normalizes_only_allowed_safe_fields(): void
    {
        $payload = (
            new IntelbrasAccessUserPayloadNormalizer
        )->normalize([
            'external_user_id' => 'visitor-004',
            'display_name' => 'VISITANTE SINTÉTICO',
            'user_type' => '2',
            'authority' => '2',
            'door_numbers' => ['2', '1', '2'],
            'time_section_numbers' => ['255', '10'],
            'valid_from' => '2026-07-31 08:00:00',
            'valid_to' => '2026-07-31 18:00:00',
            'password' => 'NAO-DEVE-SER-TRANSPORTADO',
            'photo_data' => 'NAO-DEVE-SER-TRANSPORTADO',
            'face_data' => ['landmarks' => [1, 2, 3]],
            'embedding' => [0.1, 0.2],
            'template' => 'NAO-DEVE-SER-TRANSPORTADO',
            'raw_payload' => ['unsafe' => true],
        ]);

        $this->assertInstanceOf(
            IntelbrasAccessUserPayload::class,
            $payload
        );

        $this->assertSame(
            [
                'UserList' => [
                    [
                        'UserID' => 'visitor-004',
                        'UserName' => 'VISITANTE SINTÉTICO',
                        'UserType' => 2,
                        'Authority' => 2,
                        'Doors' => [1, 2],
                        'TimeSections' => [10, 255],
                        'ValidFrom' => '2026-07-31 08:00:00',
                        'ValidTo' => '2026-07-31 18:00:00',
                    ],
                ],
            ],
            $payload->toIntelbrasPayload()
        );

        $serialized = $payload->toDeterministicJson();

        foreach (
            [
                'Password',
                'PhotoData',
                'FaceData',
                'embedding',
                'template',
                'raw_payload',
                'landmarks',
                'NAO-DEVE-SER-TRANSPORTADO',
            ] as $forbiddenValue
        ) {
            $this->assertStringNotContainsString(
                $forbiddenValue,
                $serialized
            );
        }
    }

    public function test_it_rejects_a_missing_required_field(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        (
            new IntelbrasAccessUserPayloadNormalizer
        )->normalize([
            'external_user_id' => 'visitor-005',
        ]);
    }

    public function test_it_rejects_a_malformed_date(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        (
            new IntelbrasAccessUserPayloadNormalizer
        )->normalize([
            'external_user_id' => 'visitor-006',
            'display_name' => 'VISITANTE',
            'user_type' => 2,
            'authority' => 2,
            'door_numbers' => [1],
            'time_section_numbers' => [255],
            'valid_from' => '31/07/2026 08:00',
            'valid_to' => '2026-07-31 18:00:00',
        ]);
    }

    public function test_it_rejects_non_list_collections(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        (
            new IntelbrasAccessUserPayloadNormalizer
        )->normalize([
            'external_user_id' => 'visitor-007',
            'display_name' => 'VISITANTE',
            'user_type' => 2,
            'authority' => 2,
            'door_numbers' => [
                'first' => 1,
            ],
            'time_section_numbers' => [255],
            'valid_from' => '2026-07-31 08:00:00',
            'valid_to' => '2026-07-31 18:00:00',
        ]);
    }
}
