<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Integrations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProviderException;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Modules\Identity\Infrastructure\Integrations\CnpjLookup\BrasilApiCnpjLookupProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrasilApiCnpjLookupProviderTest extends TestCase
{
    public function test_it_implements_the_cnpj_lookup_provider_contract(): void
    {
        $provider = new BrasilApiCnpjLookupProvider;

        $this->assertInstanceOf(CnpjLookupProvider::class, $provider);
        $this->assertSame('brasilapi', $provider->name());
    }

    public function test_it_fetches_and_normalizes_cnpj_data_from_brasilapi(): void
    {
        Http::fake([
            'https://brasilapi.test/api/cnpj/v1/11222333000181' => Http::response([
                'cnpj' => '11222333000181',
                'razao_social' => 'Agronorte Distribuidora',
                'nome_fantasia' => 'Agronorte',
                'situacao_cadastral' => '02',
                'descricao_situacao_cadastral' => 'Ativa',
                'data_situacao_cadastral' => '2020-01-01',
                'motivo_situacao_cadastral' => null,
                'codigo_natureza_juridica' => '2062',
                'natureza_juridica' => 'Sociedade Empresária Limitada',
                'codigo_porte' => '05',
                'descricao_porte' => 'Demais',
                'data_inicio_atividade' => '2020-01-01',
                'capital_social' => '100000.00',
                'cep' => '76900000',
                'logradouro' => 'Rua Exemplo',
                'numero' => '100',
                'complemento' => 'Sala 1',
                'bairro' => 'Centro',
                'municipio' => 'Ji-Paraná',
                'codigo_municipio_ibge' => '1100122',
                'uf' => 'RO',
                'email' => 'contato@agronorte.test',
                'ddd_telefone_1' => '6934210000',
                'ddd_telefone_2' => null,
                'ddd_fax' => null,
                'cnae_fiscal' => '4691500',
                'cnae_fiscal_descricao' => 'Comércio atacadista de mercadorias em geral',
                'cnaes_secundarios' => [
                    [
                        'codigo' => '4623109',
                        'descricao' => 'Comércio atacadista de alimentos para animais',
                    ],
                ],
                'qsa' => [
                    [
                        'nome_socio' => 'Sócio Exemplo',
                        'qualificacao_socio' => 'Administrador',
                    ],
                ],
                'opcao_pelo_simples' => true,
                'data_opcao_pelo_simples' => '2020-01-01',
                'data_exclusao_do_simples' => null,
                'opcao_pelo_mei' => false,
                'data_opcao_pelo_mei' => null,
                'data_exclusao_do_mei' => null,
            ], 200),
        ]);

        $provider = new BrasilApiCnpjLookupProvider('https://brasilapi.test');

        $result = $provider->lookup(new Cnpj('11.222.333/0001-81'));

        $this->assertSame('brasilapi', $result->provider);
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
        $this->assertSame('Ji-Paraná', $result->normalizedPayload['address']['city']);
        $this->assertSame('RO', $result->normalizedPayload['address']['state']);
        $this->assertSame('contato@agronorte.test', $result->normalizedPayload['contacts']['email']);
        $this->assertSame('4691500', $result->normalizedPayload['cnae']['primary_code']);
        $this->assertSame(true, $result->normalizedPayload['tax_regime']['is_simples_nacional']);
        $this->assertSame(false, $result->normalizedPayload['tax_regime']['is_mei']);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://brasilapi.test/api/cnpj/v1/11222333000181');
    }

    public function test_it_throws_provider_exception_when_brasilapi_returns_error(): void
    {
        Http::fake([
            'https://brasilapi.test/api/cnpj/v1/11222333000181' => Http::response([
                'message' => 'CNPJ não encontrado.',
            ], 404),
        ]);

        $provider = new BrasilApiCnpjLookupProvider('https://brasilapi.test');

        try {
            $provider->lookup(new Cnpj('11.222.333/0001-81'));

            $this->fail('Expected provider exception was not thrown.');
        } catch (CnpjLookupProviderException $exception) {
            $this->assertSame('brasilapi', $exception->provider());
            $this->assertSame('BrasilAPI CNPJ lookup failed with HTTP status 404.', $exception->getMessage());
            $this->assertSame(404, $exception->httpStatus());
            $this->assertSame('11222333000181', $exception->context()['cnpj']);
            $this->assertSame('/api/cnpj/v1/11222333000181', $exception->context()['endpoint']);
            $this->assertSame(['message' => 'CNPJ não encontrado.'], $exception->context()['response']);
        }
    }
}
