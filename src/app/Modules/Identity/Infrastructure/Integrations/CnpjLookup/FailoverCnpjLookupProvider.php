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

        foreach ($this->providers as $index => $provider) {
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

                return $this->enrichResultIfNeeded(
                    cnpj: $cnpj,
                    result: $result,
                    nextProviderIndex: $index + 1,
                );
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

    private function enrichResultIfNeeded(
        Cnpj $cnpj,
        CnpjLookupResult $result,
        int $nextProviderIndex,
    ): CnpjLookupResult {
        if (! $this->shouldTryEnrichment($result)) {
            return $result;
        }

        $enrichedResult = $result;

        for ($index = $nextProviderIndex; $index < count($this->providers); $index++) {
            $provider = $this->providers[$index];

            $startedAt = hrtime(true);
            $requestedAt = new DateTimeImmutable;

            try {
                $candidate = $provider->lookup($cnpj);

                $respondedAt = new DateTimeImmutable;
                $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

                $this->attempts[] = CnpjLookupAttempt::success(
                    provider: $provider->name(),
                    result: $candidate,
                    requestedAt: $requestedAt,
                    respondedAt: $respondedAt,
                    durationMs: $durationMs,
                );

                $enrichedResult = $this->mergeResults($enrichedResult, $candidate);

                if (! $this->shouldTryEnrichment($enrichedResult)) {
                    return $enrichedResult;
                }
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
            }
        }

        return $enrichedResult;
    }

    private function shouldTryEnrichment(CnpjLookupResult $result): bool
    {
        $contacts = $result->normalizedPayload['contacts'] ?? null;

        if (! is_array($contacts)) {
            return false;
        }

        return ! $this->hasFilledValue($contacts['email'] ?? null);
    }

    private function mergeResults(CnpjLookupResult $primary, CnpjLookupResult $secondary): CnpjLookupResult
    {
        return new CnpjLookupResult(
            provider: $primary->provider,
            cnpj: $primary->cnpj,
            legalName: $this->firstFilled($primary->legalName, $secondary->legalName),
            tradeName: $this->firstFilled($primary->tradeName, $secondary->tradeName),
            registrationStatusCode: $this->firstFilled($primary->registrationStatusCode, $secondary->registrationStatusCode),
            registrationStatusName: $this->firstFilled($primary->registrationStatusName, $secondary->registrationStatusName),
            legalNatureCode: $this->firstFilled($primary->legalNatureCode, $secondary->legalNatureCode),
            legalNatureName: $this->firstFilled($primary->legalNatureName, $secondary->legalNatureName),
            companySizeCode: $this->firstFilled($primary->companySizeCode, $secondary->companySizeCode),
            companySizeName: $this->firstFilled($primary->companySizeName, $secondary->companySizeName),
            openedAt: $this->firstFilled($primary->openedAt, $secondary->openedAt),
            shareCapital: $this->firstFilled($primary->shareCapital, $secondary->shareCapital),
            normalizedPayload: $this->mergeMissingValues(
                primary: $primary->normalizedPayload,
                secondary: $secondary->normalizedPayload,
            ),
            rawPayload: [
                'primary_provider' => $primary->provider,
                'primary_payload' => $primary->rawPayload,
                'enrichment_provider' => $secondary->provider,
                'enrichment_payload' => $secondary->rawPayload,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $primary
     * @param  array<string, mixed>  $secondary
     * @return array<string, mixed>
     */
    private function mergeMissingValues(array $primary, array $secondary): array
    {
        foreach ($secondary as $key => $secondaryValue) {
            $primaryValue = $primary[$key] ?? null;

            if (
                is_array($primaryValue)
                && is_array($secondaryValue)
                && ! array_is_list($primaryValue)
                && ! array_is_list($secondaryValue)
            ) {
                $primary[$key] = $this->mergeMissingValues($primaryValue, $secondaryValue);

                continue;
            }

            if (! $this->hasFilledValue($primaryValue) && $this->hasFilledValue($secondaryValue)) {
                $primary[$key] = $secondaryValue;
            }
        }

        return $primary;
    }

    private function firstFilled(mixed $primary, mixed $secondary): mixed
    {
        return $this->hasFilledValue($primary)
            ? $primary
            : $secondary;
    }

    private function hasFilledValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
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
