<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Users;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users\IntelbrasAccessUserPayload;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Tests\TestCase;

final class IntelbrasAccessUserPayloadTest extends TestCase
{
    public function test_it_serializes_a_deterministic_safe_payload(): void
    {
        $payload = new IntelbrasAccessUserPayload(
            externalUserId: 'visitor-001',
            displayName: 'VISITANTE SINTÉTICO',
            userType: IntelbrasAccessUserPayload::USER_TYPE_GUEST,
            authority: IntelbrasAccessUserPayload::AUTHORITY_STANDARD_USER,
            doorNumbers: [2, 1, 2],
            timeSectionNumbers: [255, 10, 255],
            validFrom: $this->date('2026-07-31 08:00:00'),
            validTo: $this->date('2026-07-31 18:00:00'),
        );

        $this->assertSame(
            [
                'UserList' => [
                    [
                        'UserID' => 'visitor-001',
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

        $this->assertSame(
            '{"UserList":[{"UserID":"visitor-001","UserName":"VISITANTE SINTÉTICO","UserType":2,"Authority":2,"Doors":[1,2],"TimeSections":[10,255],"ValidFrom":"2026-07-31 08:00:00","ValidTo":"2026-07-31 18:00:00"}]}',
            $payload->toDeterministicJson()
        );
    }

    public function test_it_never_exposes_sensitive_or_biometric_fields(): void
    {
        $payload = new IntelbrasAccessUserPayload(
            externalUserId: 'visitor-002',
            displayName: 'VISITANTE SEGURO',
            userType: IntelbrasAccessUserPayload::USER_TYPE_GENERAL,
            authority: IntelbrasAccessUserPayload::AUTHORITY_STANDARD_USER,
            doorNumbers: [1],
            timeSectionNumbers: [255],
            validFrom: $this->date('2026-07-31 08:00:00'),
            validTo: $this->date('2026-07-31 18:00:00'),
        );

        $user = $payload->toIntelbrasPayload()['UserList'][0];

        foreach (
            [
                'Password',
                'PhotoData',
                'FaceData',
                'Template',
                'Embedding',
                'RawPayload',
            ] as $forbiddenKey
        ) {
            $this->assertArrayNotHasKey(
                $forbiddenKey,
                $user
            );
        }
    }

    public function test_it_rejects_invalid_identity_and_policy_values(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasAccessUserPayload(
            externalUserId: '../visitor',
            displayName: 'VISITANTE',
            userType: 99,
            authority: 99,
            doorNumbers: [0],
            timeSectionNumbers: [-1],
            validFrom: $this->date('2026-07-31 18:00:00'),
            validTo: $this->date('2026-07-31 08:00:00'),
        );
    }

    public function test_it_rejects_an_invalid_validity_window(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasAccessUserPayload(
            externalUserId: 'visitor-003',
            displayName: 'VISITANTE',
            userType: IntelbrasAccessUserPayload::USER_TYPE_GUEST,
            authority: IntelbrasAccessUserPayload::AUTHORITY_STANDARD_USER,
            doorNumbers: [1],
            timeSectionNumbers: [255],
            validFrom: $this->date('2026-07-31 18:00:00'),
            validTo: $this->date('2026-07-31 18:00:00'),
        );
    }

    private function date(
        string $value
    ): DateTimeImmutable {
        return new DateTimeImmutable(
            $value,
            new DateTimeZone('UTC')
        );
    }
}
