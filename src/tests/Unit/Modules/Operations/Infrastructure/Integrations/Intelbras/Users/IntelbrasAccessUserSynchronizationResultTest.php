<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Users;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users\IntelbrasAccessUserSynchronizationResult;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users\IntelbrasAccessUserSynchronizationStatus;
use InvalidArgumentException;
use Tests\TestCase;

final class IntelbrasAccessUserSynchronizationResultTest extends TestCase
{
    public function test_it_represents_a_fail_closed_blocked_result(): void
    {
        $result = IntelbrasAccessUserSynchronizationResult::blocked(
            'visitor-001'
        );

        $this->assertSame(
            IntelbrasAccessUserSynchronizationStatus::Blocked,
            $result->status
        );

        $this->assertTrue($result->isBlocked());
        $this->assertFalse($result->isSimulated());
        $this->assertFalse($result->wasTransportAttempted());

        $this->assertSame(
            [
                'status' => 'blocked',
                'status_label' => 'Bloqueada',
                'external_user_id' => 'visitor-001',
                'transport_attempted' => false,
                'payload_fingerprint' => null,
                'message' => 'A sincronização Intelbras está desativada por segurança.',
            ],
            $result->toSafeArray()
        );
    }

    public function test_it_represents_a_safe_simulated_result(): void
    {
        $fingerprint = str_repeat('a', 64);

        $result = IntelbrasAccessUserSynchronizationResult::simulated(
            externalUserId: 'visitor-002',
            payloadFingerprint: $fingerprint,
        );

        $this->assertSame(
            IntelbrasAccessUserSynchronizationStatus::Simulated,
            $result->status
        );

        $this->assertFalse($result->isBlocked());
        $this->assertTrue($result->isSimulated());
        $this->assertFalse($result->wasTransportAttempted());
        $this->assertSame(
            $fingerprint,
            $result->payloadFingerprint
        );
    }

    public function test_it_rejects_an_invalid_simulation_fingerprint(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        IntelbrasAccessUserSynchronizationResult::simulated(
            externalUserId: 'visitor-003',
            payloadFingerprint: 'invalid',
        );
    }
}
