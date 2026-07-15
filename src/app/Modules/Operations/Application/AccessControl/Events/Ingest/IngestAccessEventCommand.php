<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Ingest;

use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Support\Contracts\Command;
use DateTimeInterface;

final readonly class IngestAccessEventCommand implements Command
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $accessDeviceId,
        public string $externalEventId,
        public ?string $externalPersonId,
        public AccessEventDirection $direction,
        public DateTimeInterface $occurredAt,
        public array $payload = [],
        public string $eventType = 'face_recognition',
    ) {}
}
