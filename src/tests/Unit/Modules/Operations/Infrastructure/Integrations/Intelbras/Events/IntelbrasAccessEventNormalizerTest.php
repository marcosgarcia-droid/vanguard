<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Events;

use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Events\IntelbrasAccessEventNormalizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Events\IntelbrasAccessEventReceiveException;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Illuminate\Support\Str;
use Tests\TestCase;

final class IntelbrasAccessEventNormalizerTest extends TestCase
{
    public function test_it_normalizes_a_minimal_event(): void
    {
        [
            $deviceDirection,
            $expectedEventDirection,
        ] = $this->unidirectionalDirection();

        $device = $this->device(
            $deviceDirection
        );

        $command = app(
            IntelbrasAccessEventNormalizer::class
        )->normalize(
            [
                'eventId' => 'intelbras-event-001',
                'userId' => 'visitor-123',
                'eventTime' => '2026-07-18 10:30:00',
                'eventType' => 'access',
                'result' => 'allowed',
            ],
            $device
        );

        $this->assertSame(
            $device->id,
            $command->accessDeviceId
        );

        $this->assertSame(
            'intelbras-event-001',
            $command->externalEventId
        );

        $this->assertSame(
            'visitor-123',
            $command->externalPersonId
        );

        $this->assertSame(
            $expectedEventDirection,
            $command->direction
        );

        $this->assertSame(
            'face_recognition',
            $command->eventType
        );

        $this->assertSame(
            [
                'source' => 'intelbras',
                'synthetic' => false,
                'sequence' => 'intelbras-event-001',
                'event_kind' => 'access',
                'result' => 'allowed',
            ],
            $command->payload
        );
    }

    public function test_it_reads_nested_event_fields(): void
    {
        [
            $deviceDirection,
            $expectedEventDirection,
        ] = $this->unidirectionalDirection();

        $device = $this->device(
            $deviceDirection
        );

        $command = app(
            IntelbrasAccessEventNormalizer::class
        )->normalize(
            [
                'data' => [
                    'eventId' => 'nested-event-001',
                    'personId' => 'person-456',
                    'eventTime' => '2026-07-18T11:45:00-03:00',
                    'eventType' => 'face',
                    'status' => 'granted',
                ],
            ],
            $device
        );

        $this->assertSame(
            'nested-event-001',
            $command->externalEventId
        );

        $this->assertSame(
            'person-456',
            $command->externalPersonId
        );

        $this->assertSame(
            $expectedEventDirection,
            $command->direction
        );

        $this->assertSame(
            'face',
            $command->payload['event_kind']
        );

        $this->assertSame(
            'granted',
            $command->payload['result']
        );
    }

    public function test_it_generates_a_deterministic_fallback_identifier(): void
    {
        [
            $deviceDirection,
        ] = $this->unidirectionalDirection();

        $device = $this->device(
            $deviceDirection
        );

        $normalizer = app(
            IntelbrasAccessEventNormalizer::class
        );

        $first = $normalizer->normalize(
            [
                'userId' => 'visitor-123',
                'eventTime' => '2026-07-18 12:00:00',
                'eventType' => 'access',
                'photo' => 'first-image-content',
            ],
            $device
        );

        $second = $normalizer->normalize(
            [
                'userId' => 'visitor-123',
                'eventTime' => '2026-07-18 12:00:00',
                'eventType' => 'access',
                'photo' => 'different-image-content',
            ],
            $device
        );

        $this->assertStringStartsWith(
            'intelbras-',
            $first->externalEventId
        );

        $this->assertSame(
            $first->externalEventId,
            $second->externalEventId
        );
    }

    public function test_it_rejects_a_json_list(): void
    {
        [
            $deviceDirection,
        ] = $this->unidirectionalDirection();

        $device = $this->device(
            $deviceDirection
        );

        $this->expectException(
            IntelbrasAccessEventReceiveException::class
        );

        $this->expectExceptionMessage(
            'O evento Intelbras precisa ser um objeto JSON válido.'
        );

        app(
            IntelbrasAccessEventNormalizer::class
        )->normalize(
            [
                ['eventId' => 'event-001'],
            ],
            $device
        );
    }

    public function test_it_requires_direction_for_a_bidirectional_device(): void
    {
        $deviceDirection = $this->bidirectionalDirection();

        if (! $deviceDirection instanceof AccessDeviceDirection) {
            $this->markTestSkipped(
                'O domínio atual não possui direção bidirecional.'
            );
        }

        $device = $this->device(
            $deviceDirection
        );

        $this->expectException(
            IntelbrasAccessEventReceiveException::class
        );

        $this->expectExceptionMessage(
            'A direção é obrigatória para um dispositivo bidirecional.'
        );

        app(
            IntelbrasAccessEventNormalizer::class
        )->normalize(
            [
                'eventId' => 'event-001',
                'eventTime' => '2026-07-18 12:00:00',
            ],
            $device
        );
    }

    /**
     * @return array{
     *     0: AccessDeviceDirection,
     *     1: AccessEventDirection
     * }
     */
    private function unidirectionalDirection(): array
    {
        foreach (
            AccessDeviceDirection::cases() as $deviceDirection
        ) {
            $accepted = $this->acceptedDirections(
                $deviceDirection
            );

            if (count($accepted) === 1) {
                return [
                    $deviceDirection,
                    $accepted[0],
                ];
            }
        }

        self::fail(
            'Nenhuma direção unidirecional foi encontrada no domínio.'
        );
    }

    private function bidirectionalDirection(): ?AccessDeviceDirection
    {
        foreach (
            AccessDeviceDirection::cases() as $deviceDirection
        ) {
            if (
                count(
                    $this->acceptedDirections(
                        $deviceDirection
                    )
                ) > 1
            ) {
                return $deviceDirection;
            }
        }

        return null;
    }

    /**
     * @return array<int, AccessEventDirection>
     */
    private function acceptedDirections(
        AccessDeviceDirection $deviceDirection
    ): array {
        return array_values(
            array_filter(
                AccessEventDirection::cases(),
                fn (
                    AccessEventDirection $eventDirection
                ): bool => $deviceDirection->accepts(
                    $eventDirection
                )
            )
        );
    }

    private function device(
        AccessDeviceDirection $direction
    ): AccessDeviceRecord {
        $device = new AccessDeviceRecord;

        $device->forceFill([
            'id' => (string) Str::uuid(),
            'provider' => 'intelbras',
            'direction' => $direction,
            'settings' => [],
        ]);

        return $device;
    }
}
