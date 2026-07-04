<?php

namespace App\Modules\Identity\Infrastructure\Integrations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupAttempt;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupAttemptAwareProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProviderException;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

final class FailoverCnpjLookupProvider implements CnpjLookupAttemptAwareProvider
{
    /**
     * @var list<CnpjLookupProvider>
     */
    private array $providers;

    /**
     * @var list<CnpjLookupAttempt>
     */
    private array $attempts = [];

    /**
     * @param  iterable<CnpjLookupProvider>  $providers
     */
    public function __construct(iterable $providers)
    {
        $resolvedProviders = [];

        foreach ($providers as $provider) {
            if (! $provider instanceof CnpjLookupProvider) {
                throw new InvalidArgumentException('All CNPJ lookup providers must implement CnpjLookupProvider.');
            }

            $resolvedProviders[] = $provider;
        }

        if ($resolvedProviders === []) {
            throw new InvalidArgumentException('At least one CNPJ lookup provider must be configured.');
        }

        $this->providers = $resolvedProviders;
    }

    public function name(): string
    {
        return 'failover-cnpj';
    }

    public function lookup(Cnpj $cnpj): CnpjLookupResult
    {
        $this->attempts = [];

        $failures = [];
        $lastException = null;

        foreach ($this->providers as $provider) {
            $startedAt = hrtime(true);
            $requestedAt = new DateTimeImmutable;

            try {
                $result = $provider->lookup($cnpj);

                $respondedAt = new DateTimeImmutable;
                $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

                $this->attempts[] = CnpjLookupAttempt::success(
                    provider: $provider->name(),
                    result: $result,
                    requestedAt: $requestedAt,
                    respondedAt: $respondedAt,
                    durationMs: $durationMs,
                );

                return $result;
            } catch (Throwable $exception) {
                $respondedAt = new DateTimeImmutable;
                $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

                $failure = $this->failureContext($provider, $exception);

                $this->attempts[] = CnpjLookupAttempt::failed(
                    provider: $provider->name(),
                    exception: $exception,
                    requestedAt: $requestedAt,
                    respondedAt: $respondedAt,
                    durationMs: $durationMs,
                    context: $failure,
                );

                $failures[] = $failure;
                $lastException = $exception;
            }
        }

        throw CnpjLookupProviderException::failed(
            provider: $this->name(),
            message: 'All CNPJ lookup providers failed.',
            context: [
                'cnpj' => $cnpj->value(),
                'attempts' => $failures,
            ],
            previous: $lastException,
        );
    }

    /**
     * @return list<CnpjLookupAttempt>
     */
    public function attempts(): array
    {
        return $this->attempts;
    }

    /**
     * @return array<string, mixed>
     */
    private function failureContext(CnpjLookupProvider $provider, Throwable $exception): array
    {
        $context = [
            'provider' => $provider->name(),
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ];

        if ($exception instanceof CnpjLookupProviderException) {
            $context['http_status'] = $exception->httpStatus();
            $context['context'] = $exception->context();
        }

        return $context;
    }
}
