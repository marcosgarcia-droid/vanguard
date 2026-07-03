<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use PHPUnit\Framework\TestCase;

class CnpjLookupProviderTest extends TestCase
{
    public function test_it_defines_a_contract_for_cnpj_lookup_providers(): void
    {
        $provider = new class implements CnpjLookupProvider
        {
            public function lookup(Cnpj $cnpj): CnpjLookupResult
            {
                return new CnpjLookupResult(
                    provider: 'fake-provider',
                    cnpj: $cnpj->value(),
                    legalName: 'Agronorte Distribuidora',
                    rawPayload: [
                        'cnpj' => $cnpj->value(),
                    ],
                );
            }
        };

        $result = $provider->lookup(new Cnpj('11.222.333/0001-81'));

        $this->assertSame('fake-provider', $result->provider);
        $this->assertSame('11222333000181', $result->cnpj);
        $this->assertSame('Agronorte Distribuidora', $result->legalName);
        $this->assertSame(['cnpj' => '11222333000181'], $result->rawPayload);
    }
}
