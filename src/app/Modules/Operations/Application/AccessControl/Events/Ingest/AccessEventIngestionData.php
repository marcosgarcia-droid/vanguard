<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Ingest;

use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use DateTimeInterface;

final readonly class AccessEventIngestionData
{
    /**
     * @param  array<string, bool|float|int|string>  $payload
     */
    public function __construct(
        public string $deviceId,
        public string $externalEventId,
        public ?string $externalPersonId,
        public string $eventType,
        public AccessEventDirection $direction,
        public DateTimeInterface $occurredAt,
        public AccessEventStatus $status,
        public string $resultCode,
        public string $resultMessage,
        public array $payload,
    ) {}
}
