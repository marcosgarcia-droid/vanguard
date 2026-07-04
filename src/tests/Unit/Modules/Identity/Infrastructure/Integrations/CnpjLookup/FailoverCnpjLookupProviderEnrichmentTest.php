<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Integrations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Modules\Identity\Infrastructure\Integrations\CnpjLookup\FailoverCnpjLookupProvider;
use PHPUnit\Framework\TestCase;

class FailoverCnpjLookupProviderEnrichmentTest extends TestCase
{
    public function test_it_enriches_missing_email_with_next_successful_provider(): void
    {
        $provider = new FailoverCnpjLookupProvider([
            new class implements CnpjLookupProvider
            {
                public function name(): string
                {
                    return 'brasilapi';
                }

                public function lookup(Cnpj $cnpj): CnpjLookupResult
                {
                    return new CnpjLookupResult(
                        provider: $this->name(),
                        cnpj: $cnpj->value(),
                        legalName: 'AGRONORTE NUTRICAO ANIMAL LTDA',
                        tradeName: 'RACOES AGRONORTE',
                        normalizedPayload: [
                            'cnpj' => $cnpj->value(),
                            'contacts' => [
                                'email' => null,
                                'phone_1' => '6334712155',
                            ],
                        ],
                        rawPayload: [
                            'email' => null,
                            'ddd_telefone_1' => '6334712155',
                        ],
                    );
                }
            },
            new class implements CnpjLookupProvider
            {
                public function name(): string
                {
                    return 'receitaws';
                }

                public function lookup(Cnpj $cnpj): CnpjLookupResult
                {
                    return new CnpjLookupResult(
                        provider: $this->name(),
                        cnpj: $cnpj->value(),
                        legalName: 'AGRONORTE NUTRICAO ANIMAL LTDA',
                        tradeName: 'RACOES AGRONORTE',
                        normalizedPayload: [
                            'cnpj' => $cnpj->value(),
                            'contacts' => [
                                'email' => 'marcela@agronorte.net',
                                'phone_1' => '(63) 3471-2155',
                            ],
                        ],
                        rawPayload: [
                            'email' => 'marcela@agronorte.net',
                            'telefone' => '(63) 3471-2155',
                        ],
                    );
                }
            },
        ]);

        $result = $provider->lookup(new Cnpj('11.222.333/0001-81'));

        $this->assertSame('brasilapi', $result->provider);
        $this->assertSame('marcela@agronorte.net', $result->normalizedPayload['contacts']['email']);
        $this->assertSame('6334712155', $result->normalizedPayload['contacts']['phone_1']);

        $attempts = $provider->attempts();

        $this->assertCount(2, $attempts);
        $this->assertTrue($attempts[0]->isSuccess());
        $this->assertTrue($attempts[1]->isSuccess());
        $this->assertSame('brasilapi', $attempts[0]->provider);
        $this->assertSame('receitaws', $attempts[1]->provider);
    }
}
