<?php

namespace App\Modules\Operations\Application\AccessControl\Events\ManualReview;

use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use Carbon\CarbonImmutable;

final readonly class RecordAccessEventManualReviewResult
{
    public function __construct(
        public string $reviewId,
        public string $eventId,
        public string $decisionId,
        public AccessEventManualReviewDisposition $disposition,
        public CarbonImmutable $reviewedAt,
        public bool $duplicate,
    ) {}
}
