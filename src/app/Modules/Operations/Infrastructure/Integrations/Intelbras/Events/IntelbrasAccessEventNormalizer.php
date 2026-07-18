<?php

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Events;

use App\Modules\Operations\Application\AccessControl\Events\Ingest\IngestAccessEventCommand;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;
use JsonException;

final readonly class IntelbrasAccessEventNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalize(
        array $payload,
        AccessDeviceRecord $device
    ): IngestAccessEventCommand {
        if ($payload === [] || array_is_list($payload)) {
            throw new IntelbrasAccessEventReceiveException(
                'O evento Intelbras precisa ser um objeto JSON válido.'
            );
        }

        $direction = $this->resolveDirection(
            $payload,
            $device
        );

        $occurredAt = $this->resolveOccurredAt(
            $payload
        );

        $externalPersonId = $this->optionalString(
            $this->firstScalar(
                $payload,
                [
                    'userId',
                    'user_id',
                    'UserID',
                    'personId',
                    'person_id',
                    'person.id',
                    'user.id',
                    'event.userId',
                    'event.user_id',
                    'event.personId',
                    'data.userId',
                    'data.user_id',
                    'data.personId',
                    'data.person.id',
                    'data.user.id',
                ]
            ),
            255
        );

        $eventKind = $this->optionalString(
            $this->firstScalar(
                $payload,
                [
                    'eventKind',
                    'event_kind',
                    'eventType',
                    'event_type',
                    'type',
                    'event.type',
                    'event.eventType',
                    'data.type',
                    'data.eventType',
                    'data.event_type',
                ]
            ),
            255
        ) ?? 'access_event';

        $result = $this->optionalString(
            $this->firstScalar(
                $payload,
                [
                    'result',
                    'status',
                    'accessResult',
                    'access_result',
                    'event.result',
                    'event.status',
                    'data.result',
                    'data.status',
                    'data.accessResult',
                ]
            ),
            255
        ) ?? 'received';

        $externalEventId = $this->optionalString(
            $this->firstScalar(
                $payload,
                [
                    'id',
                    'eventId',
                    'event_id',
                    'EventID',
                    'sequence',
                    'serialNo',
                    'serial_no',
                    'event.id',
                    'event.eventId',
                    'event.event_id',
                    'data.id',
                    'data.eventId',
                    'data.event_id',
                    'data.sequence',
                    'data.serialNo',
                ]
            ),
            191
        );

        if ($externalEventId === null) {
            $externalEventId = $this->fallbackEventId(
                $payload,
                $device->id
            );
        }

        return new IngestAccessEventCommand(
            accessDeviceId: $device->id,
            externalEventId: $externalEventId,
            externalPersonId: $externalPersonId,
            direction: $direction,
            occurredAt: $occurredAt,
            payload: [
                'source' => 'intelbras',
                'synthetic' => false,
                'sequence' => $externalEventId,
                'event_kind' => $eventKind,
                'result' => $result,
            ],
            eventType: 'face_recognition',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveDirection(
        array $payload,
        AccessDeviceRecord $device
    ): AccessEventDirection {
        $deviceDirection = $device->direction;

        if (! $deviceDirection instanceof AccessDeviceDirection) {
            $deviceDirection = AccessDeviceDirection::tryFrom(
                (string) $deviceDirection
            );
        }

        if (! $deviceDirection instanceof AccessDeviceDirection) {
            throw new IntelbrasAccessEventReceiveException(
                'O dispositivo possui uma direção inválida.'
            );
        }

        $acceptedDirections = array_values(
            array_filter(
                AccessEventDirection::cases(),
                fn (
                    AccessEventDirection $eventDirection
                ): bool => $deviceDirection->accepts(
                    $eventDirection
                )
            )
        );

        if ($acceptedDirections === []) {
            throw new IntelbrasAccessEventReceiveException(
                'O dispositivo não aceita direções de eventos.'
            );
        }

        $reportedDirection = $this->optionalString(
            $this->firstScalar(
                $payload,
                [
                    'direction',
                    'doorDirection',
                    'door_direction',
                    'passageDirection',
                    'passage_direction',
                    'event.direction',
                    'event.doorDirection',
                    'data.direction',
                    'data.doorDirection',
                    'data.passageDirection',
                ]
            ),
            50
        );

        if ($reportedDirection === null) {
            if (count($acceptedDirections) === 1) {
                return $acceptedDirections[0];
            }

            throw new IntelbrasAccessEventReceiveException(
                'A direção é obrigatória para um dispositivo bidirecional.'
            );
        }

        $reportedSemantic = $this->directionSemantic(
            $reportedDirection
        );

        if ($reportedSemantic === null) {
            throw new IntelbrasAccessEventReceiveException(
                'A direção informada pelo equipamento não foi reconhecida.'
            );
        }

        foreach ($acceptedDirections as $acceptedDirection) {
            $caseSemantic = $this->directionSemantic(
                $acceptedDirection->name
                .' '
                .$acceptedDirection->value
            );

            if ($caseSemantic === $reportedSemantic) {
                return $acceptedDirection;
            }
        }

        throw new IntelbrasAccessEventReceiveException(
            'A direção informada é incompatível com o dispositivo.'
        );
    }

    private function directionSemantic(
        string $value
    ): ?string {
        $normalized = Str::of(
            Str::ascii($value)
        )
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();

        if (
            in_array(
                $normalized,
                [
                    '1',
                    'in',
                    'entry',
                    'entrada',
                    'checkin',
                    'inbound',
                    'ingress',
                ],
                true
            )
            || str_contains($normalized, 'entrada')
            || str_contains($normalized, 'entry')
            || str_contains($normalized, 'checkin')
            || str_contains($normalized, 'inbound')
        ) {
            return 'entry';
        }

        if (
            in_array(
                $normalized,
                [
                    '2',
                    'out',
                    'exit',
                    'saida',
                    'checkout',
                    'outbound',
                    'egress',
                ],
                true
            )
            || str_contains($normalized, 'saida')
            || str_contains($normalized, 'exit')
            || str_contains($normalized, 'checkout')
            || str_contains($normalized, 'outbound')
        ) {
            return 'exit';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveOccurredAt(
        array $payload
    ): DateTimeInterface {
        $value = $this->firstScalar(
            $payload,
            [
                'occurredAt',
                'occurred_at',
                'eventTime',
                'event_time',
                'dateTime',
                'datetime',
                'time',
                'timestamp',
                'event.occurredAt',
                'event.eventTime',
                'event.time',
                'event.timestamp',
                'data.occurredAt',
                'data.eventTime',
                'data.time',
                'data.timestamp',
            ]
        );

        if ($value === null || trim((string) $value) === '') {
            return CarbonImmutable::now();
        }

        try {
            if (is_numeric($value)) {
                $timestamp = (float) $value;

                if ($timestamp > 9_999_999_999) {
                    $timestamp /= 1000;
                }

                return CarbonImmutable::createFromTimestampUTC(
                    (int) $timestamp
                )->setTimezone(
                    (string) config('app.timezone', 'UTC')
                );
            }

            return CarbonImmutable::parse(
                (string) $value,
                (string) config('app.timezone', 'UTC')
            );
        } catch (\Throwable $exception) {
            throw new IntelbrasAccessEventReceiveException(
                'A data e hora do evento não foram reconhecidas.',
                previous: $exception
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $paths
     */
    private function firstScalar(
        array $payload,
        array $paths
    ): bool|float|int|string|null {
        foreach ($paths as $path) {
            $value = data_get(
                $payload,
                $path
            );

            if (
                is_bool($value)
                || is_float($value)
                || is_int($value)
                || is_string($value)
            ) {
                return $value;
            }
        }

        return null;
    }

    private function optionalString(
        bool|float|int|string|null $value,
        int $maximumLength
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr(
            $value,
            0,
            $maximumLength
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fallbackEventId(
        array $payload,
        string $deviceId
    ): string {
        try {
            $encoded = json_encode(
                [
                    'device_id' => $deviceId,
                    'payload' => $this->fingerprintPayload(
                        $payload
                    ),
                ],
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $exception) {
            throw new IntelbrasAccessEventReceiveException(
                'Não foi possível identificar unicamente o evento.',
                previous: $exception
            );
        }

        return 'intelbras-'.hash(
            'sha256',
            $encoded
        );
    }

    /**
     * Remove imagens, faces, fotos e blocos extensos antes de calcular
     * a impressão digital utilizada apenas para idempotência.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function fingerprintPayload(
        array $payload
    ): array {
        $result = [];

        foreach ($payload as $key => $value) {
            $normalizedKey = Str::of(
                Str::ascii((string) $key)
            )
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '')
                ->toString();

            if (
                str_contains($normalizedKey, 'image')
                || str_contains($normalizedKey, 'picture')
                || str_contains($normalizedKey, 'photo')
                || str_contains($normalizedKey, 'snapshot')
                || str_contains($normalizedKey, 'base64')
                || str_contains($normalizedKey, 'faceimage')
            ) {
                continue;
            }

            if (is_array($value)) {
                $result[(string) $key] =
                    $this->fingerprintPayload($value);

                continue;
            }

            if (
                is_bool($value)
                || is_float($value)
                || is_int($value)
            ) {
                $result[(string) $key] = $value;

                continue;
            }

            if (is_string($value)) {
                $result[(string) $key] = mb_substr(
                    trim($value),
                    0,
                    1024
                );
            }
        }

        if (! array_is_list($result)) {
            ksort($result);
        }

        return $result;
    }
}
