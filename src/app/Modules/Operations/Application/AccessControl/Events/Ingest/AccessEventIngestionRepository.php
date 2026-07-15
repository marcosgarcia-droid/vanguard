<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Ingest;

interface AccessEventIngestionRepository
{
    public function findTarget(
        string $deviceId
    ): ?AccessEventIngestionTarget;

    public function persist(
        AccessEventIngestionData $data
    ): IngestAccessEventResult;
}
