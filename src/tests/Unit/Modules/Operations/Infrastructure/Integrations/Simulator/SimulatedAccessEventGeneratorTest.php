<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Simulator;

use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Infrastructure\Integrations\Simulator\SimulatedAccessEventGenerator;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class SimulatedAccessEventGeneratorTest extends TestCase
{
    public function test_it_generates_a_deterministic_safe_event(): void
    {
        config()->set(
            'access_control.simulator_enabled',
            true
        );

        $generator = app(
            SimulatedAccessEventGenerator::class
        );

        $occurredAt = new DateTimeImmutable(
            '2026-07-15 10:15:00'
        );

        $first = $generator->generate(
            deviceId: 'synthetic-device-001',
            direction: AccessEventDirection::Entry,
            sequence: 3,
            occurredAt: $occurredAt,
            externalPersonId: 'synthetic-person-001',
        );

        $second = $generator->generate(
            deviceId: 'synthetic-device-001',
            direction: AccessEventDirection::Entry,
            sequence: 3,
            occurredAt: $occurredAt,
            externalPersonId: 'synthetic-person-001',
        );

        $this->assertSame(
            $first->externalEventId,
            $second->externalEventId
        );

        $this->assertStringStartsWith(
            'simulator-',
            $first->externalEventId
        );

        $this->assertSame(
            [
                'source' => 'simulator',
                'synthetic' => true,
                'sequence' => 3,
                'event_kind' => 'face_recognition',
                'result' => 'recognized',
            ],
            $first->payload
        );

        $this->assertArrayNotHasKey(
            'face_image',
            $first->payload
        );

        $this->assertArrayNotHasKey(
            'template',
            $first->payload
        );
    }

    public function test_it_is_blocked_when_the_simulator_is_disabled(): void
    {
        config()->set(
            'access_control.simulator_enabled',
            false
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        app(
            SimulatedAccessEventGenerator::class
        )->generate(
            deviceId: 'synthetic-device-001',
            direction: AccessEventDirection::Entry,
            sequence: 1,
            occurredAt: new DateTimeImmutable,
        );
    }

    public function test_it_rejects_an_invalid_sequence(): void
    {
        config()->set(
            'access_control.simulator_enabled',
            true
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        app(
            SimulatedAccessEventGenerator::class
        )->generate(
            deviceId: 'synthetic-device-001',
            direction: AccessEventDirection::Entry,
            sequence: 0,
            occurredAt: new DateTimeImmutable,
        );
    }
}
