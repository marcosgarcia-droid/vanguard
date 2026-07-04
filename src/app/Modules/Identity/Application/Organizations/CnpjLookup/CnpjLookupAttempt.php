<?php

namespace App\Modules\Identity\Application\Organizations\CnpjLookup;

use DateTimeImmutable;
use Throwable;

final readonly class CnpjLookupAttempt
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function __construct(
        public string $provider,
        public CnpjLookupSyncStatus $status,
        public DateTimeImmutable $requestedAt,
        public DateTimeImmutable $respondedAt,
        public int $durationMs,
        public ?CnpjLookupResult $result = null,
        public ?Throwable $exception = null,
        public array $context = [],
    ) {}

    public static function success(
        string $provider,
        CnpjLookupResult $result,
        DateTimeImmutable $requestedAt,
        DateTimeImmutable $respondedAt,
        int $durationMs,
    ): self {
        return new self(
            provider: $provider,
            status: CnpjLookupSyncStatus::Success,
            requestedAt: $requestedAt,
            respondedAt: $respondedAt,
            durationMs: $durationMs,
            result: $result,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function failed(
        string $provider,
        Throwable $exception,
        DateTimeImmutable $requestedAt,
        DateTimeImmutable $respondedAt,
        int $durationMs,
        array $context = [],
    ): self {
        return new self(
            provider: $provider,
            status: CnpjLookupSyncStatus::Failed,
            requestedAt: $requestedAt,
            respondedAt: $respondedAt,
            durationMs: $durationMs,
            exception: $exception,
            context: $context,
        );
    }

    public function isSuccess(): bool
    {
        return $this->status === CnpjLookupSyncStatus::Success;
    }

    public function isFailure(): bool
    {
        return $this->status === CnpjLookupSyncStatus::Failed;
    }
}
