<?php

namespace Tests\Feature\Modules\Identity\Organizations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProviderException;
use App\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj\LookupOrganizationByCnpjCommand;
use App\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj\LookupOrganizationByCnpjUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use JsonException;
use Tests\TestCase;

class CnpjLookupFailoverIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws JsonException
     */
    public function test_it_uses_failover_provider_and_records_each_attempt(): void
    {
        Http::fake([
            'https://brasilapi.com.br/api/cnpj/v1/11222333000181' => Http::response([
                'message' => 'BrasilAPI indisponível no teste.',
            ], 503),

            'https://www.receitaws.com.br/v1/cnpj/11222333000181' => Http::response([
                'status' => 'OK',
                'cnpj' => '11.222.333/0001-81',
                'abertura' => '01/01/2020',
                'situacao' => 'ATIVA',
                'nome' => 'Agronorte Distribuidora',
                'fantasia' => 'Agronorte',
                'porte' => 'DEMAIS',
                'natureza_juridica' => '206-2 - Sociedade Empresária Limitada',
                'capital_social' => '100000.00',
                'logradouro' => 'Rua Exemplo',
                'numero' => '100',
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
                'atividades_secundarias' => [],
                'qsa' => [],
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

        $useCase = app(LookupOrganizationByCnpjUseCase::class);

        $result = $useCase->execute(new LookupOrganizationByCnpjCommand(
            cnpj: '11.222.333/0001-81',
        ));

        $this->assertSame('receitaws', $result->provider);
        $this->assertSame('Agronorte Distribuidora', $result->legalName);
        $this->assertSame('Agronorte', $result->tradeName);

        $syncs = DB::table('organization_cnpj_syncs')
            ->where('cnpj', '11222333000181')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $syncs);

        $failedResponsePayload = json_decode(
            json: $syncs[0]->response_payload,
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('brasilapi', $syncs[0]->provider);
        $this->assertSame('failed', $syncs[0]->status);
        $this->assertSame('/api/cnpj/v1/11222333000181', $syncs[0]->endpoint);
        $this->assertSame(503, $syncs[0]->http_status);
        $this->assertSame(CnpjLookupProviderException::class, $syncs[0]->error_code);
        $this->assertSame('BrasilAPI CNPJ lookup failed with HTTP status 503.', $syncs[0]->error_message);
        $this->assertSame(['message' => 'BrasilAPI indisponível no teste.'], $failedResponsePayload);
        $this->assertNull($syncs[0]->response_hash);

        $normalizedPayload = json_decode(
            json: $syncs[1]->normalized_payload,
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('receitaws', $syncs[1]->provider);
        $this->assertSame('success', $syncs[1]->status);
        $this->assertSame('Agronorte Distribuidora', $normalizedPayload['legal_name']);
        $this->assertSame('Agronorte', $normalizedPayload['trade_name']);
        $this->assertNotNull($syncs[1]->response_hash);

        Http::assertSentCount(2);
    }
}
