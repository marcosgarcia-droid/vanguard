<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Users;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users\IntelbrasAccessUserPayload;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users\IntelbrasAccessUserSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users\SimulatedIntelbrasAccessUserSynchronizer;
use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

final class SimulatedIntelbrasAccessUserSynchronizerTest extends TestCase
{
    public function test_it_generates_a_deterministic_dry_run_result(): void
    {
        $synchronizer =
            new SimulatedIntelbrasAccessUserSynchronizer;

        $this->assertInstanceOf(
            IntelbrasAccessUserSynchronizer::class,
            $synchronizer
        );

        $payload = $this->payload(
            externalUserId: 'visitor-simulated-001',
            displayName: 'VISITANTE SIMULADO',
        );

        $firstResult = $synchronizer->synchronize(
            $payload
        );

        $secondResult = $synchronizer->synchronize(
            $payload
        );

        $expectedFingerprint = hash(
            'sha256',
            $payload->toDeterministicJson()
        );

        $this->assertTrue($firstResult->isSimulated());
        $this->assertFalse($firstResult->isBlocked());
        $this->assertFalse(
            $firstResult->wasTransportAttempted()
        );

        $this->assertSame(
            $expectedFingerprint,
            $firstResult->payloadFingerprint
        );

        $this->assertSame(
            $firstResult->payloadFingerprint,
            $secondResult->payloadFingerprint
        );
    }

    public function test_it_changes_the_fingerprint_when_the_payload_changes(): void
    {
        $synchronizer =
            new SimulatedIntelbrasAccessUserSynchronizer;

        $first = $synchronizer->synchronize(
            $this->payload(
                externalUserId: 'visitor-simulated-002',
                displayName: 'VISITANTE UM',
            )
        );

        $second = $synchronizer->synchronize(
            $this->payload(
                externalUserId: 'visitor-simulated-002',
                displayName: 'VISITANTE DOIS',
            )
        );

        $this->assertNotSame(
            $first->payloadFingerprint,
            $second->payloadFingerprint
        );
    }

    public function test_safe_result_never_exposes_the_payload(): void
    {
        $result = (
            new SimulatedIntelbrasAccessUserSynchronizer
        )->synchronize(
            $this->payload(
                externalUserId: 'visitor-simulated-003',
                displayName: 'VISITANTE SEGURO',
            )
        );

        $serialized = json_encode(
            $result->toSafeArray(),
            JSON_THROW_ON_ERROR
        );

        foreach (
            [
                'UserList',
                'UserName',
                'Doors',
                'TimeSections',
                'ValidFrom',
                'ValidTo',
                'Password',
                'PhotoData',
                'FaceData',
                'Embedding',
                'Template',
            ] as $forbiddenValue
        ) {
            $this->assertStringNotContainsString(
                $forbiddenValue,
                $serialized
            );
        }
    }

    private function payload(
        string $externalUserId,
        string $displayName,
    ): IntelbrasAccessUserPayload {
        return new IntelbrasAccessUserPayload(
            externalUserId: $externalUserId,
            displayName: $displayName,
            userType: IntelbrasAccessUserPayload::USER_TYPE_GUEST,
            authority: IntelbrasAccessUserPayload::AUTHORITY_STANDARD_USER,
            doorNumbers: [1, 2],
            timeSectionNumbers: [255],
            validFrom: new DateTimeImmutable(
                '2026-07-31 08:00:00',
                new DateTimeZone('UTC')
            ),
            validTo: new DateTimeImmutable(
                '2026-07-31 18:00:00',
                new DateTimeZone('UTC')
            ),
        );
    }
}
