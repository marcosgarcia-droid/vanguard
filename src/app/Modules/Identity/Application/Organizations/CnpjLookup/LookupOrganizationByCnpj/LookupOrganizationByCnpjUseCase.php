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

final readonly class LookupOrganizationByCnpjUseCase implements UseCase
{
    public function __construct(
        private CnpjLookupProvider $provider,
        private CnpjLookupSyncRepository $syncs,
        private TransactionManager $transactions,
    ) {}

    public function execute(LookupOrganizationByCnpjCommand $command): CnpjLookupResult
    {
        return $this->transactions->run(function () use ($command): CnpjLookupResult {
            $startedAt = hrtime(true);
            $requestedAt = new DateTimeImmutable;
            $cnpj = new Cnpj($command->cnpj);

            $result = $this->provider->lookup($cnpj);

            $respondedAt = new DateTimeImmutable;
            $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            $this->syncs->save(CnpjLookupSync::success(
                cnpj: $cnpj,
                provider: $result->provider,
                responsePayload: $result->rawPayload,
                normalizedPayload: $result->normalizedPayload,
                organizationId: $command->organizationId,
                requestedAt: $requestedAt,
                respondedAt: $respondedAt,
                durationMs: $durationMs,
                requestPayload: [
                    'cnpj' => $cnpj->value(),
                    'cnpj_formatted' => $cnpj->formatted(),
                ],
                responseHash: $this->hashPayload($result->rawPayload),
            ));

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
