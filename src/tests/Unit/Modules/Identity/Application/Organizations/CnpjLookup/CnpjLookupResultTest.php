<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use PHPUnit\Framework\TestCase;

class CnpjLookupResultTest extends TestCase
{
    public function test_it_represents_normalized_cnpj_lookup_data(): void
    {
        $result = new CnpjLookupResult(
            provider: 'fake-provider',
            cnpj: '11222333000181',
            legalName: 'Agronorte Distribuidora',
            tradeName: 'Agronorte',
            registrationStatusCode: '02',
            registrationStatusName: 'Ativa',
            legalNatureCode: '2062',
            legalNatureName: 'Sociedade Empresária Limitada',
            companySizeCode: '05',
            companySizeName: 'Demais',
            openedAt: '2020-01-01',
            shareCapital: '100000.00',
            normalizedPayload: [
                'cnpj' => '11222333000181',
            ],
            rawPayload: [
                'source' => 'example',
            ],
        );

        $this->assertSame('fake-provider', $result->provider);
        $this->assertSame('11222333000181', $result->cnpj);
        $this->assertSame('Agronorte Distribuidora', $result->legalName);
        $this->assertSame('Agronorte', $result->tradeName);
        $this->assertSame('02', $result->registrationStatusCode);
        $this->assertSame('Ativa', $result->registrationStatusName);
        $this->assertSame('2062', $result->legalNatureCode);
        $this->assertSame('Sociedade Empresária Limitada', $result->legalNatureName);
        $this->assertSame('05', $result->companySizeCode);
        $this->assertSame('Demais', $result->companySizeName);
        $this->assertSame('2020-01-01', $result->openedAt);
        $this->assertSame('100000.00', $result->shareCapital);
        $this->assertSame(['cnpj' => '11222333000181'], $result->normalizedPayload);
        $this->assertSame(['source' => 'example'], $result->rawPayload);
    }
}
