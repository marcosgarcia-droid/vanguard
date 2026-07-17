<?php

namespace App\Modules\Operations\Application\AccessControl\Events\ManualReview;

use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;

final readonly class RecordAccessEventManualReviewCommand
{
    public function __construct(
        public string $eventId,
        public int $operatorUserId,
        public AccessEventManualReviewDisposition $disposition,
        public string $notes,
        public string $idempotencyKey,
    ) {}
}
