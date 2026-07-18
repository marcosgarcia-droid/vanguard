<?php

namespace Tests\Feature\Modules\Operations\Integrations\Intelbras;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use Database\Seeders\VanguardAccessDeviceDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ReceiveIntelbrasAccessEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'access_control.intelbras_post_events_enabled',
            true
        );

        Http::preventStrayRequests();
    }

    public function test_it_receives_and_persists_an_intelbras_event_idempotently(): void
    {
        $token = str_repeat('a', 64);

        $device = $this->intelbrasDevice(
            $token
        );

        $url = route(
            'integrations.intelbras.access-events.store',
            [
                'device' => $device->id,
                'token' => $token,
            ]
        );

        $payload = [
            'eventId' => 'intelbras-post-event-001',
            'userId' => 'intelbras-person-001',
            'eventTime' => '2026-07-18 14:30:00',
            'eventType' => 'access',
            'result' => 'allowed',
            'photo' => 'biometric-content-must-not-be-stored',
        ];

        $firstResponse = $this->postJson(
            $url,
            $payload
        );

        $firstResponse
            ->assertOk()
            ->assertExactJson([
                'status' => 'accepted',
            ]);

        $event = AccessEventRecord::query()
            ->where(
                'access_device_id',
                $device->id
            )
            ->where(
                'external_event_id',
                'intelbras-post-event-001'
            )
            ->firstOrFail();

        $this->assertSame(
            'intelbras-person-001',
            $event->external_person_id
        );

        $this->assertSame(
            'face_recognition',
            $event->event_type
        );

        $this->assertSame(
            [
                'source' => 'intelbras',
                'synthetic' => false,
                'sequence' => 'intelbras-post-event-001',
                'event_kind' => 'access',
                'result' => 'allowed',
            ],
            $event->raw_payload
        );

        $this->assertArrayNotHasKey(
            'photo',
            $event->raw_payload
        );

        $secondResponse = $this->postJson(
            $url,
            $payload
        );

        $secondResponse
            ->assertOk()
            ->assertExactJson([
                'status' => 'duplicate',
            ]);

        $this->assertSame(
            1,
            AccessEventRecord::query()
                ->where(
                    'access_device_id',
                    $device->id
                )
                ->where(
                    'external_event_id',
                    'intelbras-post-event-001'
                )
                ->count()
        );

        $device->refresh();

        $this->assertSame(
            'success',
            $device->last_communication_status
        );

        $this->assertSame(
            'Evento Intelbras duplicado reconhecido com segurança.',
            $device->last_communication_message
        );

        $this->assertNotNull(
            $device->last_communication_at
        );

        $this->assertNotNull(
            $device->last_event_at
        );

        Http::assertSentCount(0);
    }

    public function test_it_hides_the_endpoint_for_an_invalid_token(): void
    {
        $device = $this->intelbrasDevice(
            str_repeat('a', 64)
        );

        $response = $this->postJson(
            route(
                'integrations.intelbras.access-events.store',
                [
                    'device' => $device->id,
                    'token' => str_repeat('b', 64),
                ]
            ),
            [
                'eventId' => 'intelbras-post-event-invalid-token',
            ]
        );

        $response->assertNotFound();

        $this->assertSame(
            0,
            AccessEventRecord::query()->count()
        );

        Http::assertSentCount(0);
    }

    private function intelbrasDevice(
        string $token
    ): AccessDeviceRecord {
        $this->seed(
            VanguardAccessDeviceDemoSeeder::class
        );

        $device = AccessDeviceRecord::query()
            ->where(
                'code',
                'FAC-SIM-ENT-01'
            )
            ->firstOrFail();

        $settings = is_array($device->settings)
            ? $device->settings
            : [];

        data_set(
            $settings,
            'intelbras_event_ingestion',
            [
                'enabled' => true,
                'token_hash' => hash(
                    'sha256',
                    $token
                ),
                'issued_at' => now()->toIso8601String(),
            ]
        );

        $device
            ->forceFill([
                'provider' => 'intelbras',
                'settings' => $settings,
            ])
            ->saveQuietly();

        return $device->refresh();
    }
}
