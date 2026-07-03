<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Integrations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Modules\Identity\Infrastructure\Integrations\CnpjLookup\FakeCnpjLookupProvider;
use PHPUnit\Framework\TestCase;

class FakeCnpjLookupProviderTest extends TestCase
{
    public function test_it_implements_the_cnpj_lookup_provider_contract(): void
    {
        $provider = new FakeCnpjLookupProvider;

        $this->assertInstanceOf(CnpjLookupProvider::class, $provider);
        $this->assertSame('fake-cnpj', $provider->name());
    }

    public function test_it_returns_normalized_cnpj_lookup_result(): void
    {
        $provider = new FakeCnpjLookupProvider;

        $result = $provider->lookup(new Cnpj('11.222.333/0001-81'));

        $this->assertSame('fake-cnpj', $result->provider);
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

        $this->assertSame('11.222.333/0001-81', $result->normalizedPayload['cnpj_formatted']);
        $this->assertSame('11222333', $result->normalizedPayload['cnpj_root']);
        $this->assertSame('0001', $result->normalizedPayload['cnpj_branch']);
        $this->assertSame('81', $result->normalizedPayload['cnpj_check_digits']);

        $this->assertSame('fake-cnpj', $result->rawPayload['provider']);
    }
}
