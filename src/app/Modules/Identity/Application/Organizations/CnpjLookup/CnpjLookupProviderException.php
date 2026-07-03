<?php

namespace App\Modules\Identity\Application\Organizations\CnpjLookup;

use RuntimeException;
use Throwable;

final class CnpjLookupProviderException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly string $provider,
        string $message,
        private readonly ?int $httpStatus = null,
        private readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function failed(
        string $provider,
        string $message,
        ?int $httpStatus = null,
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self(
            provider: $provider,
            message: $message,
            httpStatus: $httpStatus,
            context: $context,
            previous: $previous,
        );
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
