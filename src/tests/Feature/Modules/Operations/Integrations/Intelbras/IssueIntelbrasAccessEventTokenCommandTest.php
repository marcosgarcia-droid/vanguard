<?php

namespace Tests\Feature\Modules\Operations\Integrations\Intelbras;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Database\Seeders\VanguardAccessDeviceDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IssueIntelbrasAccessEventTokenCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_issues_and_revokes_an_intelbras_event_token(): void
    {
        $device = $this->intelbrasDevice();

        $this->artisan(
            'vanguard:access-device:intelbras-event-token',
            [
                'device' => $device->id,
            ]
        )
            ->expectsOutputToContain(
                'Token emitido com sucesso.'
            )
            ->assertSuccessful();

        $device->refresh();

        $settings = is_array($device->settings)
            ? $device->settings
            : [];

        $this->assertTrue(
            (bool) data_get(
                $settings,
                'intelbras_event_ingestion.enabled'
            )
        );

        $tokenHash = data_get(
            $settings,
            'intelbras_event_ingestion.token_hash'
        );

        $this->assertIsString($tokenHash);

        $this->assertSame(
            64,
            strlen($tokenHash)
        );

        $this->assertNotEmpty(
            data_get(
                $settings,
                'intelbras_event_ingestion.issued_at'
            )
        );

        $this->artisan(
            'vanguard:access-device:intelbras-event-token',
            [
                'device' => $device->id,
                '--revoke' => true,
            ]
        )
            ->expectsOutputToContain(
                'Token Intelbras revogado com sucesso.'
            )
            ->assertSuccessful();

        $device->refresh();

        $settings = is_array($device->settings)
            ? $device->settings
            : [];

        $this->assertNull(
            data_get(
                $settings,
                'intelbras_event_ingestion'
            )
        );
    }

    public function test_it_rejects_a_non_intelbras_device(): void
    {
        $this->seed(
            VanguardAccessDeviceDemoSeeder::class
        );

        $device = AccessDeviceRecord::query()
            ->where(
                'code',
                'FAC-SIM-ENT-01'
            )
            ->firstOrFail();

        $this->artisan(
            'vanguard:access-device:intelbras-event-token',
            [
                'device' => $device->id,
            ]
        )
            ->expectsOutputToContain(
                'O dispositivo informado não utiliza o provider Intelbras.'
            )
            ->assertFailed();
    }

    private function intelbrasDevice(): AccessDeviceRecord
    {
        $this->seed(
            VanguardAccessDeviceDemoSeeder::class
        );

        $device = AccessDeviceRecord::query()
            ->where(
                'code',
                'FAC-SIM-ENT-01'
            )
            ->firstOrFail();

        $device
            ->forceFill([
                'provider' => 'intelbras',
            ])
            ->saveQuietly();

        return $device->refresh();
    }
}
