<?php

namespace Tests\Unit\Core\Events;

use App\Core\Events\BaseDomainEvent;
use DateTimeImmutable;
use Tests\TestCase;

class BaseDomainEventTest extends TestCase
{
    public function test_it_exposes_event_metadata(): void
    {
        $occurredAt = new DateTimeImmutable('2026-01-01 10:00:00');

        $event = new class($occurredAt) extends BaseDomainEvent
        {
            public function payload(): array
            {
                return [
                    'resource_id' => 123,
                ];
            }
        };

        $this->assertSame($event::class, $event->name());
        $this->assertSame($occurredAt, $event->occurredAt());
        $this->assertSame(['resource_id' => 123], $event->payload());
    }

    public function test_it_has_an_empty_payload_by_default(): void
    {
        $event = new class extends BaseDomainEvent {};

        $this->assertSame([], $event->payload());
    }
}
