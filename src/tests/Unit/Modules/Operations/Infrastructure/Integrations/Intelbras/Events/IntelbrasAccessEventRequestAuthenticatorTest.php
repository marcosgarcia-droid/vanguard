<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Events;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Events\IntelbrasAccessEventRequestAuthenticator;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Tests\TestCase;

final class IntelbrasAccessEventRequestAuthenticatorTest extends TestCase
{
    public function test_it_authorizes_the_configured_intelbras_token(): void
    {
        $token = str_repeat('a', 64);

        $device = $this->device(
            provider: 'intelbras',
            enabled: true,
            tokenHash: hash('sha256', $token),
        );

        $authorized = app(
            IntelbrasAccessEventRequestAuthenticator::class
        )->authorize(
            $device,
            $token
        );

        $this->assertTrue($authorized);
    }

    public function test_it_rejects_an_invalid_token(): void
    {
        $device = $this->device(
            provider: 'intelbras',
            enabled: true,
            tokenHash: hash(
                'sha256',
                str_repeat('a', 64)
            ),
        );

        $authorized = app(
            IntelbrasAccessEventRequestAuthenticator::class
        )->authorize(
            $device,
            str_repeat('b', 64)
        );

        $this->assertFalse($authorized);
    }

    public function test_it_rejects_disabled_ingestion(): void
    {
        $token = str_repeat('a', 64);

        $device = $this->device(
            provider: 'intelbras',
            enabled: false,
            tokenHash: hash('sha256', $token),
        );

        $authorized = app(
            IntelbrasAccessEventRequestAuthenticator::class
        )->authorize(
            $device,
            $token
        );

        $this->assertFalse($authorized);
    }

    public function test_it_rejects_a_non_intelbras_device(): void
    {
        $token = str_repeat('a', 64);

        $device = $this->device(
            provider: 'simulator',
            enabled: true,
            tokenHash: hash('sha256', $token),
        );

        $authorized = app(
            IntelbrasAccessEventRequestAuthenticator::class
        )->authorize(
            $device,
            $token
        );

        $this->assertFalse($authorized);
    }

    private function device(
        string $provider,
        bool $enabled,
        string $tokenHash
    ): AccessDeviceRecord {
        $device = new AccessDeviceRecord;

        $device->forceFill([
            'provider' => $provider,
            'settings' => [
                'intelbras_event_ingestion' => [
                    'enabled' => $enabled,
                    'token_hash' => $tokenHash,
                ],
            ],
        ]);

        return $device;
    }
}
