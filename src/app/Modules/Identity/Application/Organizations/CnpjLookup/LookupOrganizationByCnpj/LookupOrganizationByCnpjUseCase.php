<?php

namespace App\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSync;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSyncRepository;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Support\Contracts\TransactionManager;
use App\Support\Contracts\UseCase;
use DateTimeImmutable;
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
            $respondedAt = new DateTimeImmutable;
            $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            $this->transactions->run(function () use ($command, $cnpj, $requestedAt, $respondedAt, $durationMs, $exception): void {
                $this->syncs->save(CnpjLookupSync::failed(
                    cnpj: $cnpj,
                    provider: $this->provider->name(),
                    errorMessage: $exception->getMessage(),
                    organizationId: $command->organizationId,
                    requestedAt: $requestedAt,
                    respondedAt: $respondedAt,
                    durationMs: $durationMs,
                    errorCode: $exception::class,
                    requestPayload: $this->requestPayload($cnpj),
                ));
            });

            throw $exception;
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
