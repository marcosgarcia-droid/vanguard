<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Reprocess;

final readonly class ReprocessAccessEventFlowContext
{
    public function __construct(
        public string $eventId,
        public bool $manualReviewReleaseUsed,
        public ?string $decisionId,
        public ?string $manualReviewId,
    ) {}
}
