<?php

namespace Tests\Feature\Modules\Operations\Integrations\Intelbras;

use Illuminate\Support\Str;
use Tests\TestCase;

final class ReceiveIntelbrasAccessEventRouteTest extends TestCase
{
    public function test_the_intelbras_event_route_is_registered(): void
    {
        $url = route(
            'integrations.intelbras.access-events.store',
            [
                'device' => (string) Str::uuid(),
                'token' => str_repeat('a', 64),
            ]
        );

        $this->assertStringContainsString(
            '/api/integrations/intelbras/access-events/',
            $url
        );
    }

    public function test_the_endpoint_is_hidden_when_disabled(): void
    {
        config()->set(
            'access_control.intelbras_post_events_enabled',
            false
        );

        $response = $this->postJson(
            route(
                'integrations.intelbras.access-events.store',
                [
                    'device' => (string) Str::uuid(),
                    'token' => str_repeat('a', 64),
                ]
            ),
            [
                'eventId' => 'event-001',
            ]
        );

        $response->assertNotFound();
    }
}
