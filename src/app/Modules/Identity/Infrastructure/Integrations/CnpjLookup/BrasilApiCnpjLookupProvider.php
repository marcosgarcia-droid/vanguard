<?php

namespace App\Modules\Identity\Infrastructure\Integrations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProviderException;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class BrasilApiCnpjLookupProvider implements CnpjLookupProvider
{
    public function __construct(
        private string $baseUrl = 'https://brasilapi.com.br',
    ) {}

    public function name(): string
    {
        return 'brasilapi';
    }

    public function lookup(Cnpj $cnpj): CnpjLookupResult
    {
        $endpoint = '/api/cnpj/v1/'.$cnpj->value();

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->acceptJson()
                ->timeout(10)
                ->get($endpoint);
        } catch (Throwable $exception) {
            throw CnpjLookupProviderException::failed(
                provider: $this->name(),
                message: 'BrasilAPI CNPJ lookup request failed.',
                context: [
                    'cnpj' => $cnpj->value(),
                    'endpoint' => $endpoint,
                ],
                previous: $exception,
            );
        }

        if (! $response->successful()) {
            throw CnpjLookupProviderException::failed(
                provider: $this->name(),
                message: sprintf('BrasilAPI CNPJ lookup failed with HTTP status %s.', $response->status()),
                httpStatus: $response->status(),
                context: [
                    'cnpj' => $cnpj->value(),
                    'endpoint' => $endpoint,
                    'response' => $response->json(),
                ],
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw CnpjLookupProviderException::failed(
                provider: $this->name(),
                message: 'BrasilAPI CNPJ lookup returned an invalid response.',
                httpStatus: $response->status(),
                context: [
                    'cnpj' => $cnpj->value(),
                    'endpoint' => $endpoint,
                ],
            );
        }

        return new CnpjLookupResult(
            provider: $this->name(),
            cnpj: $cnpj->value(),
            legalName: $this->string($payload, 'razao_social'),
            tradeName: $this->string($payload, 'nome_fantasia'),
            registrationStatusCode: $this->string($payload, 'situacao_cadastral'),
            registrationStatusName: $this->string($payload, 'descricao_situacao_cadastral'),
            legalNatureCode: $this->string($payload, 'codigo_natureza_juridica'),
            legalNatureName: $this->string($payload, 'natureza_juridica'),
            companySizeCode: $this->string($payload, 'codigo_porte'),
            companySizeName: $this->string($payload, 'descricao_porte') ?? $this->string($payload, 'porte'),
            openedAt: $this->string($payload, 'data_inicio_atividade'),
            shareCapital: $this->string($payload, 'capital_social'),
            normalizedPayload: $this->normalizedPayload($payload, $cnpj),
            rawPayload: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function string(array $payload, string $key): ?string
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        $value = trim((string) $payload[$key]);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizedPayload(array $payload, Cnpj $cnpj): array
    {
        return [
            'cnpj' => $cnpj->value(),
            'cnpj_formatted' => $cnpj->formatted(),
            'cnpj_root' => $cnpj->root(),
            'cnpj_branch' => $cnpj->branch(),
            'cnpj_check_digits' => $cnpj->checkDigits(),

            'legal_name' => $this->string($payload, 'razao_social'),
            'trade_name' => $this->string($payload, 'nome_fantasia'),

            'registration_status_code' => $this->string($payload, 'situacao_cadastral'),
            'registration_status_name' => $this->string($payload, 'descricao_situacao_cadastral'),
            'registration_status_date' => $this->string($payload, 'data_situacao_cadastral'),
            'registration_status_reason' => $this->string($payload, 'motivo_situacao_cadastral'),

            'legal_nature_code' => $this->string($payload, 'codigo_natureza_juridica'),
            'legal_nature_name' => $this->string($payload, 'natureza_juridica'),

            'company_size_code' => $this->string($payload, 'codigo_porte'),
            'company_size_name' => $this->string($payload, 'descricao_porte') ?? $this->string($payload, 'porte'),

            'opened_at' => $this->string($payload, 'data_inicio_atividade'),
            'share_capital' => $this->string($payload, 'capital_social'),

            'address' => [
                'postal_code' => $this->string($payload, 'cep'),
                'street' => $this->string($payload, 'logradouro'),
                'number' => $this->string($payload, 'numero'),
                'complement' => $this->string($payload, 'complemento'),
                'district' => $this->string($payload, 'bairro'),
                'city' => $this->string($payload, 'municipio'),
                'city_code' => $this->string($payload, 'codigo_municipio_ibge'),
                'state' => $this->string($payload, 'uf'),
                'country_code' => 'BR',
            ],

            'contacts' => [
                'email' => $this->string($payload, 'email'),
                'phone_1' => $this->string($payload, 'ddd_telefone_1'),
                'phone_2' => $this->string($payload, 'ddd_telefone_2'),
                'fax' => $this->string($payload, 'ddd_fax'),
            ],

            'cnae' => [
                'primary_code' => $this->string($payload, 'cnae_fiscal'),
                'primary_description' => $this->string($payload, 'cnae_fiscal_descricao'),
                'secondary' => $payload['cnaes_secundarios'] ?? [],
            ],

            'members' => $payload['qsa'] ?? [],

            'tax_regime' => [
                'is_simples_nacional' => $payload['opcao_pelo_simples'] ?? null,
                'simples_nacional_opted_at' => $this->string($payload, 'data_opcao_pelo_simples'),
                'simples_nacional_excluded_at' => $this->string($payload, 'data_exclusao_do_simples'),
                'is_mei' => $payload['opcao_pelo_mei'] ?? null,
                'mei_opted_at' => $this->string($payload, 'data_opcao_pelo_mei'),
                'mei_excluded_at' => $this->string($payload, 'data_exclusao_do_mei'),
            ],
        ];
    }
}
