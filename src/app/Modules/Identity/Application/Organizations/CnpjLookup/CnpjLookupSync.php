<?php

namespace App\Modules\Identity\Application\Organizations\CnpjLookup;

use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use DateTimeImmutable;

final readonly class CnpjLookupSync
{
    public function __construct(
        public Cnpj $cnpj,
        public string $provider,
        public CnpjLookupSyncStatus $status,
        public ?string $organizationId = null,
        public ?string $endpoint = null,
        public ?int $httpStatus = null,
        public ?DateTimeImmutable $requestedAt = null,
        public ?DateTimeImmutable $respondedAt = null,
        public ?int $durationMs = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $requestPayload = [],
        public array $responsePayload = [],
        public array $normalizedPayload = [],
        public ?string $responseHash = null,
    ) {}

    public static function success(
        Cnpj $cnpj,
        string $provider,
        array $responsePayload,
        array $normalizedPayload,
        ?string $organizationId = null,
        ?string $endpoint = null,
        ?int $httpStatus = null,
        ?DateTimeImmutable $requestedAt = null,
        ?DateTimeImmutable $respondedAt = null,
        ?int $durationMs = null,
        array $requestPayload = [],
        ?string $responseHash = null,
    ): self {
        return new self(
            cnpj: $cnpj,
            provider: $provider,
            status: CnpjLookupSyncStatus::Success,
            organizationId: $organizationId,
            endpoint: $endpoint,
            httpStatus: $httpStatus,
            requestedAt: $requestedAt,
            respondedAt: $respondedAt,
            durationMs: $durationMs,
            requestPayload: $requestPayload,
            responsePayload: $responsePayload,
            normalizedPayload: $normalizedPayload,
            responseHash: $responseHash,
        );
    }

    public static function failed(
        Cnpj $cnpj,
        string $provider,
        string $errorMessage,
        ?string $organizationId = null,
        ?string $endpoint = null,
        ?int $httpStatus = null,
        ?DateTimeImmutable $requestedAt = null,
        ?DateTimeImmutable $respondedAt = null,
        ?int $durationMs = null,
        ?string $errorCode = null,
        array $requestPayload = [],
        array $responsePayload = [],
    ): self {
        return new self(
            cnpj: $cnpj,
            provider: $provider,
            status: CnpjLookupSyncStatus::Failed,
            organizationId: $organizationId,
            endpoint: $endpoint,
            httpStatus: $httpStatus,
            requestedAt: $requestedAt,
            respondedAt: $respondedAt,
            durationMs: $durationMs,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            requestPayload: $requestPayload,
            responsePayload: $responsePayload,
        );
    }
}
