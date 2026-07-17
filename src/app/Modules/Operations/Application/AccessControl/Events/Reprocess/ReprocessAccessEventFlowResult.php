<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Reprocess;

use App\Modules\Operations\Application\AccessControl\Events\Orchestrate\ContinueAccessEventFlowResult;

final readonly class ReprocessAccessEventFlowResult
{
    public function __construct(
        public ContinueAccessEventFlowResult $flow,
        public bool $manualReviewReleaseUsed,
        public ?string $decisionId,
        public ?string $manualReviewId,
        public ?string $manualReviewConsumptionId,
    ) {}
}
