<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSync;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSyncRepository;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use PHPUnit\Framework\TestCase;

class CnpjLookupSyncRepositoryTest extends TestCase
{
    public function test_it_defines_a_contract_for_recording_cnpj_lookup_syncs(): void
    {
        $repository = new class implements CnpjLookupSyncRepository
        {
            /**
             * @var list<CnpjLookupSync>
             */
            public array $syncs = [];

            public function save(CnpjLookupSync $sync): void
            {
                $this->syncs[] = $sync;
            }
        };

        $sync = CnpjLookupSync::success(
            cnpj: new Cnpj('11.222.333/0001-81'),
            provider: 'fake-provider',
            responsePayload: [
                'razao_social' => 'Agronorte Distribuidora',
            ],
            normalizedPayload: [
                'legal_name' => 'Agronorte Distribuidora',
            ],
            organizationId: 'org-001',
            httpStatus: 200,
        );

        $repository->save($sync);

        $this->assertCount(1, $repository->syncs);
        $this->assertSame('11222333000181', $repository->syncs[0]->cnpj->value());
        $this->assertSame('fake-provider', $repository->syncs[0]->provider);
        $this->assertSame('org-001', $repository->syncs[0]->organizationId);
        $this->assertSame(200, $repository->syncs[0]->httpStatus);
    }
}
