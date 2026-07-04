<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Integrations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProviderException;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Modules\Identity\Infrastructure\Integrations\CnpjLookup\ReceitaWsCnpjLookupProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReceitaWsCnpjLookupProviderTest extends TestCase
{
    public function test_it_implements_the_cnpj_lookup_provider_contract(): void
    {
        $provider = new ReceitaWsCnpjLookupProvider;

        $this->assertInstanceOf(CnpjLookupProvider::class, $provider);
        $this->assertSame('receitaws', $provider->name());
    }

    public function test_it_fetches_and_normalizes_cnpj_data_from_receitaws(): void
    {
        Http::fake([
            'https://receitaws.test/v1/cnpj/11222333000181' => Http::response([
                'status' => 'OK',
                'cnpj' => '11.222.333/0001-81',
                'abertura' => '01/01/2020',
                'situacao' => 'ATIVA',
                'data_situacao' => '01/01/2020',
                'tipo' => 'MATRIZ',
                'nome' => 'Agronorte Distribuidora',
                'fantasia' => 'Agronorte',
                'porte' => 'DEMAIS',
                'natureza_juridica' => '206-2 - Sociedade Empresária Limitada',
                'capital_social' => '100000.00',
                'logradouro' => 'Rua Exemplo',
                'numero' => '100',
                'complemento' => 'Sala 1',
                'bairro' => 'Centro',
                'municipio' => 'Ji-Paraná',
                'uf' => 'RO',
                'cep' => '76.900-000',
                'email' => 'contato@agronorte.test',
                'telefone' => '(69) 3421-0000',
                'atividade_principal' => [
                    [
                        'code' => '46.91-5-00',
                        'text' => 'Comércio atacadista de mercadorias em geral',
                    ],
                ],
                'atividades_secundarias' => [
                    [
                        'code' => '46.23-1-09',
                        'text' => 'Comércio atacadista de alimentos para animais',
                    ],
                ],
                'qsa' => [
                    [
                        'nome' => 'Sócio Exemplo',
                        'qual' => 'Administrador',
                    ],
                ],
                'simples' => [
                    'optante' => true,
                    'data_opcao' => '01/01/2020',
                    'data_exclusao' => null,
                ],
                'simei' => [
                    'optante' => false,
                    'data_opcao' => null,
                    'data_exclusao' => null,
                ],
            ], 200),
        ]);

        $provider = new ReceitaWsCnpjLookupProvider('https://receitaws.test');

        $result = $provider->lookup(new Cnpj('11.222.333/0001-81'));

        $this->assertSame('receitaws', $result->provider);
        $this->assertSame('11222333000181', $result->cnpj);
        $this->assertSame('Agronorte Distribuidora', $result->legalName);
        $this->assertSame('Agronorte', $result->tradeName);
        $this->assertNull($result->registrationStatusCode);
        $this->assertSame('ATIVA', $result->registrationStatusName);
        $this->assertSame('206-2', $result->legalNatureCode);
        $this->assertSame('206-2 - Sociedade Empresária Limitada', $result->legalNatureName);
        $this->assertNull($result->companySizeCode);
        $this->assertSame('DEMAIS', $result->companySizeName);
        $this->assertSame('2020-01-01', $result->openedAt);
        $this->assertSame('100000.00', $result->shareCapital);

        $this->assertSame('11.222.333/0001-81', $result->normalizedPayload['cnpj_formatted']);
        $this->assertSame('11222333', $result->normalizedPayload['cnpj_root']);
        $this->assertSame('0001', $result->normalizedPayload['cnpj_branch']);
        $this->assertSame('81', $result->normalizedPayload['cnpj_check_digits']);
        $this->assertSame('Ji-Paraná', $result->normalizedPayload['address']['city']);
        $this->assertSame('RO', $result->normalizedPayload['address']['state']);
        $this->assertSame('contato@agronorte.test', $result->normalizedPayload['contacts']['email']);
        $this->assertSame('46.91-5-00', $result->normalizedPayload['cnae']['primary_code']);
        $this->assertSame(true, $result->normalizedPayload['tax_regime']['is_simples_nacional']);
        $this->assertSame(false, $result->normalizedPayload['tax_regime']['is_mei']);
        $this->assertSame('2020-01-01', $result->normalizedPayload['tax_regime']['simples_nacional_opted_at']);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://receitaws.test/v1/cnpj/11222333000181');
    }

    public function test_it_throws_provider_exception_when_receitaws_returns_http_error(): void
    {
        Http::fake([
            'https://receitaws.test/v1/cnpj/11222333000181' => Http::response([
                'message' => 'Too many requests.',
            ], 429),
        ]);

        $provider = new ReceitaWsCnpjLookupProvider('https://receitaws.test');

        try {
            $provider->lookup(new Cnpj('11.222.333/0001-81'));

            $this->fail('Expected provider exception was not thrown.');
        } catch (CnpjLookupProviderException $exception) {
            $this->assertSame('receitaws', $exception->provider());
            $this->assertSame('ReceitaWS CNPJ lookup failed with HTTP status 429.', $exception->getMessage());
            $this->assertSame(429, $exception->httpStatus());
            $this->assertSame('11222333000181', $exception->context()['cnpj']);
            $this->assertSame('/v1/cnpj/11222333000181', $exception->context()['endpoint']);
            $this->assertSame(['message' => 'Too many requests.'], $exception->context()['response']);
        }
    }

    public function test_it_throws_provider_exception_when_receitaws_returns_error_status(): void
    {
        Http::fake([
            'https://receitaws.test/v1/cnpj/11222333000181' => Http::response([
                'status' => 'ERROR',
                'message' => 'CNPJ não encontrado em cache.',
            ], 200),
        ]);

        $provider = new ReceitaWsCnpjLookupProvider('https://receitaws.test');

        try {
            $provider->lookup(new Cnpj('11.222.333/0001-81'));

            $this->fail('Expected provider exception was not thrown.');
        } catch (CnpjLookupProviderException $exception) {
            $this->assertSame('receitaws', $exception->provider());
            $this->assertSame('CNPJ não encontrado em cache.', $exception->getMessage());
            $this->assertSame(200, $exception->httpStatus());
            $this->assertSame('11222333000181', $exception->context()['cnpj']);
            $this->assertSame('/v1/cnpj/11222333000181', $exception->context()['endpoint']);
            $this->assertSame('ERROR', $exception->context()['response']['status']);
        }
    }
}
