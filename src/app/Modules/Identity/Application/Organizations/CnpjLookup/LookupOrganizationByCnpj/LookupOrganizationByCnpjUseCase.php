<?php

namespace App\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupAttempt;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupAttemptAwareProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProviderException;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSync;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSyncRepository;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Support\Contracts\TransactionManager;
use App\Support\Contracts\UseCase;
use DateTimeImmutable;
use LogicException;
use Throwable;

final readonly class LookupOrganizationByCnpjUseCase implements UseCase
{
    public function __construct(
        private CnpjLookupProvider $provider,
        private CnpjLookupSyncRepository $syncs,
        private TransactionManager $transactions,
    ) {}

    public function execute(LookupOrganizationByCnpjCommand $command): CnpjLookupResult
    {
        $startedAt = hrtime(true);
        $requestedAt = new DateTimeImmutable;
        $cnpj = new Cnpj($command->cnpj);

        try {
            $result = $this->provider->lookup($cnpj);
        } catch (Throwable $exception) {
            $attempts = $this->providerAttempts();

            if ($attempts !== []) {
                $this->transactions->run(function () use ($command, $cnpj, $attempts): void {
                    $this->recordAttempts(
                        cnpj: $cnpj,
                        organizationId: $command->organizationId,
                        attempts: $attempts,
                    );
                });

                throw $exception;
            }

            $respondedAt = new DateTimeImmutable;
            $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            $this->transactions->run(function () use ($command, $cnpj, $requestedAt, $respondedAt, $durationMs, $exception): void {
                $this->syncs->save(CnpjLookupSync::failed(
                    cnpj: $cnpj,
                    provider: $this->provider->name(),
                    errorMessage: $exception->getMessage(),
                    organizationId: $command->organizationId,
                    endpoint: $this->exceptionEndpoint($exception),
                    httpStatus: $this->exceptionHttpStatus($exception),
                    requestedAt: $requestedAt,
                    respondedAt: $respondedAt,
                    durationMs: $durationMs,
                    errorCode: $exception::class,
                    requestPayload: $this->requestPayload($cnpj),
                    responsePayload: $this->exceptionResponsePayload($exception),
                ));
            });

            throw $exception;
        }

        $attempts = $this->providerAttempts();

        if ($attempts !== []) {
            $this->transactions->run(function () use ($command, $cnpj, $attempts): void {
                $this->recordAttempts(
                    cnpj: $cnpj,
                    organizationId: $command->organizationId,
                    attempts: $attempts,
                );
            });

            return $result;
        }

        $respondedAt = new DateTimeImmutable;
        $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        return $this->transactions->run(function () use ($command, $cnpj, $result, $requestedAt, $respondedAt, $durationMs): CnpjLookupResult {
            $this->syncs->save(CnpjLookupSync::success(
                cnpj: $cnpj,
                provider: $result->provider,
                responsePayload: $result->rawPayload,
                normalizedPayload: $result->normalizedPayload,
                organizationId: $command->organizationId,
                requestedAt: $requestedAt,
                respondedAt: $respondedAt,
                durationMs: $durationMs,
                requestPayload: $this->requestPayload($cnpj),
                responseHash: $this->hashPayload($result->rawPayload),
            ));

            return $result;
        });
    }

    /**
     * @return list<CnpjLookupAttempt>
     */
    private function providerAttempts(): array
    {
        if (! $this->provider instanceof CnpjLookupAttemptAwareProvider) {
            return [];
        }

        return $this->provider->attempts();
    }

    /**
     * @param  list<CnpjLookupAttempt>  $attempts
     */
    private function recordAttempts(Cnpj $cnpj, ?string $organizationId, array $attempts): void
    {
        foreach ($attempts as $attempt) {
            if ($attempt->isSuccess()) {
                $this->recordSuccessfulAttempt($cnpj, $organizationId, $attempt);

                continue;
            }

            $this->recordFailedAttempt($cnpj, $organizationId, $attempt);
        }
    }

    private function recordSuccessfulAttempt(Cnpj $cnpj, ?string $organizationId, CnpjLookupAttempt $attempt): void
    {
        if (! $attempt->result instanceof CnpjLookupResult) {
            throw new LogicException('Successful CNPJ lookup attempt must contain a result.');
        }

        $this->syncs->save(CnpjLookupSync::success(
            cnpj: $cnpj,
            provider: $attempt->provider,
            responsePayload: $attempt->result->rawPayload,
            normalizedPayload: $attempt->result->normalizedPayload,
            organizationId: $organizationId,
            requestedAt: $attempt->requestedAt,
            respondedAt: $attempt->respondedAt,
            durationMs: $attempt->durationMs,
            requestPayload: $this->requestPayload($cnpj),
            responseHash: $this->hashPayload($attempt->result->rawPayload),
        ));
    }

    private function recordFailedAttempt(Cnpj $cnpj, ?string $organizationId, CnpjLookupAttempt $attempt): void
    {
        if (! $attempt->exception instanceof Throwable) {
            throw new LogicException('Failed CNPJ lookup attempt must contain an exception.');
        }

        $this->syncs->save(CnpjLookupSync::failed(
            cnpj: $cnpj,
            provider: $attempt->provider,
            errorMessage: $attempt->exception->getMessage(),
            organizationId: $organizationId,
            endpoint: $this->exceptionEndpoint($attempt->exception),
            httpStatus: $this->exceptionHttpStatus($attempt->exception),
            requestedAt: $attempt->requestedAt,
            respondedAt: $attempt->respondedAt,
            durationMs: $attempt->durationMs,
            errorCode: $attempt->exception::class,
            requestPayload: $this->requestPayload($cnpj),
            responsePayload: $this->exceptionResponsePayload($attempt->exception),
        ));
    }

    private function exceptionEndpoint(Throwable $exception): ?string
    {
        if (! $exception instanceof CnpjLookupProviderException) {
            return null;
        }

        $endpoint = $exception->context()['endpoint'] ?? null;

        return is_string($endpoint) ? $endpoint : null;
    }

    private function exceptionHttpStatus(Throwable $exception): ?int
    {
        if (! $exception instanceof CnpjLookupProviderException) {
            return null;
        }

        return $exception->httpStatus();
    }

    /**
     * @return array<string, mixed>
     */
    private function exceptionResponsePayload(Throwable $exception): array
    {
        if (! $exception instanceof CnpjLookupProviderException) {
            return [];
        }

        $response = $exception->context()['response'] ?? [];

        return is_array($response) ? $response : [];
    }

    /**
     * @return array<string, string>
     */
    private function requestPayload(Cnpj $cnpj): array
    {
        return [
            'cnpj' => $cnpj->value(),
            'cnpj_formatted' => $cnpj->formatted(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
