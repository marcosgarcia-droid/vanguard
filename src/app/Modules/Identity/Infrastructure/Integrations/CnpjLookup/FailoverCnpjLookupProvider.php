<?php

namespace App\Modules\Identity\Infrastructure\Integrations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProviderException;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use InvalidArgumentException;
use Throwable;

final readonly class FailoverCnpjLookupProvider implements CnpjLookupProvider
{
    /**
     * @var list<CnpjLookupProvider>
     */
    private array $providers;

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
        $failures = [];
        $lastException = null;

        foreach ($this->providers as $provider) {
            try {
                return $provider->lookup($cnpj);
            } catch (Throwable $exception) {
                $failures[] = $this->failureContext($provider, $exception);
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
