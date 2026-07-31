<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Users;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users\DisabledIntelbrasAccessUserSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users\IntelbrasAccessUserPayload;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users\IntelbrasAccessUserSynchronizer;
use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

final class DisabledIntelbrasAccessUserSynchronizerTest extends TestCase
{
    public function test_it_always_blocks_without_attempting_transport(): void
    {
        $synchronizer =
            new DisabledIntelbrasAccessUserSynchronizer;

        $this->assertInstanceOf(
            IntelbrasAccessUserSynchronizer::class,
            $synchronizer
        );

        $result = $synchronizer->synchronize(
            $this->payload()
        );

        $this->assertTrue($result->isBlocked());
        $this->assertFalse($result->isSimulated());
        $this->assertFalse($result->wasTransportAttempted());
        $this->assertNull($result->payloadFingerprint);
        $this->assertSame(
            'visitor-disabled-001',
            $result->externalUserId
        );
    }

    private function payload(): IntelbrasAccessUserPayload
    {
        return new IntelbrasAccessUserPayload(
            externalUserId: 'visitor-disabled-001',
            displayName: 'VISITANTE BLOQUEADO',
            userType: IntelbrasAccessUserPayload::USER_TYPE_GUEST,
            authority: IntelbrasAccessUserPayload::AUTHORITY_STANDARD_USER,
            doorNumbers: [1],
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
