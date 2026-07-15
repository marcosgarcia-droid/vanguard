<?php

namespace App\Modules\Operations\Infrastructure\Integrations\Simulator;

use App\Modules\Operations\Application\AccessControl\Events\Ingest\IngestAccessEventCommand;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

final class SimulatedAccessEventGenerator
{
    public function generate(
        string $deviceId,
        AccessEventDirection $direction,
        int $sequence,
        DateTimeInterface $occurredAt,
        ?string $externalPersonId = null,
    ): IngestAccessEventCommand {
        if (
            ! (bool) config(
                'access_control.simulator_enabled',
                false
            )
        ) {
            throw new InvalidArgumentException(
                'O simulador de dispositivos está desativado neste ambiente.'
            );
        }

        $deviceId = trim($deviceId);

        if ($deviceId === '') {
            throw new InvalidArgumentException(
                'O identificador do dispositivo é obrigatório.'
            );
        }

        if ($sequence < 1) {
            throw new InvalidArgumentException(
                'A sequência do evento sintético precisa ser maior que zero.'
            );
        }

        $occurredAtUtc = DateTimeImmutable::createFromInterface(
            $occurredAt
        )->setTimezone(
            new DateTimeZone('UTC')
        );

        $deviceFingerprint = substr(
            hash('sha256', $deviceId),
            0,
            12
        );

        $externalEventId = sprintf(
            'simulator-%s-%s-%04d',
            $deviceFingerprint,
            $occurredAtUtc->format('YmdHis'),
            $sequence
        );

        $externalPersonId = filled(
            trim((string) $externalPersonId)
        )
            ? trim((string) $externalPersonId)
            : null;

        return new IngestAccessEventCommand(
            accessDeviceId: $deviceId,
            externalEventId: $externalEventId,
            externalPersonId: $externalPersonId,
            direction: $direction,
            occurredAt: $occurredAt,
            payload: [
                'source' => 'simulator',
                'synthetic' => true,
                'sequence' => $sequence,
                'event_kind' => 'face_recognition',
                'result' => $externalPersonId !== null
                    ? 'recognized'
                    : 'unmatched',
            ],
        );
    }
}
