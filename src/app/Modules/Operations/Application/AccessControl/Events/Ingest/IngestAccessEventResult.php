<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Ingest;

use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;

final readonly class IngestAccessEventResult
{
    public function __construct(
        public string $eventId,
        public AccessEventStatus $status,
        public bool $duplicate,
    ) {}
}
