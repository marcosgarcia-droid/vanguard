<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Reprocess;

use RuntimeException;
use Throwable;

final class ReprocessAccessEventFlowException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly bool $manualReviewReleaseConsumed = false,
        public readonly ?string $manualReviewId = null,
        public readonly ?string $manualReviewConsumptionId = null,
    ) {
        parent::__construct(
            $message,
            $code,
            $previous
        );
    }
}
