<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSync;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSyncRepository;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSyncStatus;
use App\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj\LookupOrganizationByCnpjCommand;
use App\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj\LookupOrganizationByCnpjUseCase;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Support\Contracts\TransactionManager;
use PHPUnit\Framework\TestCase;

class LookupOrganizationByCnpjUseCaseTest extends TestCase
{
    public function test_it_looks_up_cnpj_records_sync_history_and_returns_result(): void
    {
        $provider = new class implements CnpjLookupProvider
        {
            public ?Cnpj $lookedUpCnpj = null;

            public function name(): string
            {
                return 'fake-provider';
            }

            public function lookup(Cnpj $cnpj): CnpjLookupResult
            {
                $this->lookedUpCnpj = $cnpj;

                return new CnpjLookupResult(
                    provider: $this->name(),
                    cnpj: $cnpj->value(),
                    legalName: 'Agronorte Distribuidora',
                    tradeName: 'Agronorte',
                    normalizedPayload: [
                        'cnpj' => $cnpj->value(),
                        'legal_name' => 'Agronorte Distribuidora',
                    ],
                    rawPayload: [
                        'razao_social' => 'Agronorte Distribuidora',
                    ],
                );
            }
        };

        $syncs = new class implements CnpjLookupSyncRepository
        {
            /**
             * @var list<CnpjLookupSync>
             */
            public array $saved = [];

            public function save(CnpjLookupSync $sync): void
            {
                $this->saved[] = $sync;
            }
        };

        $transactions = new class implements TransactionManager
        {
            public int $runs = 0;

            public function run(callable $callback): mixed
            {
                $this->runs++;

                return $callback();
            }
        };

        $useCase = new LookupOrganizationByCnpjUseCase(
            provider: $provider,
            syncs: $syncs,
            transactions: $transactions,
        );

        $result = $useCase->execute(new LookupOrganizationByCnpjCommand(
            cnpj: '11.222.333/0001-81',
            organizationId: 'org-001',
        ));

        $this->assertSame(1, $transactions->runs);
        $this->assertSame('11222333000181', $provider->lookedUpCnpj?->value());

        $this->assertSame('fake-provider', $result->provider);
        $this->assertSame('11222333000181', $result->cnpj);
        $this->assertSame('Agronorte Distribuidora', $result->legalName);
        $this->assertSame('Agronorte', $result->tradeName);

        $this->assertCount(1, $syncs->saved);

        $sync = $syncs->saved[0];

        $this->assertSame('11222333000181', $sync->cnpj->value());
        $this->assertSame('fake-provider', $sync->provider);
        $this->assertSame(CnpjLookupSyncStatus::Success, $sync->status);
        $this->assertSame('org-001', $sync->organizationId);
        $this->assertSame(['cnpj' => '11222333000181', 'cnpj_formatted' => '11.222.333/0001-81'], $sync->requestPayload);
        $this->assertSame(['razao_social' => 'Agronorte Distribuidora'], $sync->responsePayload);
        $this->assertSame(['cnpj' => '11222333000181', 'legal_name' => 'Agronorte Distribuidora'], $sync->normalizedPayload);
        $this->assertNotNull($sync->requestedAt);
        $this->assertNotNull($sync->respondedAt);
        $this->assertNotNull($sync->responseHash);
    }
}
