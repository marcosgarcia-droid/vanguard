<?php

namespace App\Modules\Operations\Application\AccessControl\Events\ManualReview;

use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;

interface RecordAccessEventManualReviewRepository
{
    public function record(
        string $eventId,
        int $operatorUserId,
        AccessEventManualReviewDisposition $disposition,
        string $notes,
        string $idempotencyKey,
    ): ?RecordAccessEventManualReviewResult;
}
