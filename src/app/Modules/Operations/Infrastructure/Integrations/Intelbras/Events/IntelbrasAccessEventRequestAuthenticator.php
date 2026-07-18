<?php

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Events;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;

final readonly class IntelbrasAccessEventRequestAuthenticator
{
    public function authorize(
        AccessDeviceRecord $device,
        string $token
    ): bool {
        if (
            strtolower(trim((string) $device->provider))
            !== 'intelbras'
        ) {
            return false;
        }

        $settings = is_array($device->settings)
            ? $device->settings
            : [];

        $enabled = (bool) data_get(
            $settings,
            'intelbras_event_ingestion.enabled',
            false
        );

        $expectedHash = data_get(
            $settings,
            'intelbras_event_ingestion.token_hash'
        );

        if (
            ! $enabled
            || ! is_string($expectedHash)
            || strlen($expectedHash) !== 64
            || trim($token) === ''
        ) {
            return false;
        }

        return hash_equals(
            $expectedHash,
            hash('sha256', $token)
        );
    }
}
