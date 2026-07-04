<?php

namespace App\Modules\Identity\Infrastructure\Integrations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProviderException;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class ReceitaWsCnpjLookupProvider implements CnpjLookupProvider
{
    public function __construct(
        private string $baseUrl = 'https://www.receitaws.com.br',
    ) {}

    public function name(): string
    {
        return 'receitaws';
    }

    public function lookup(Cnpj $cnpj): CnpjLookupResult
    {
        $endpoint = '/v1/cnpj/'.$cnpj->value();

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->acceptJson()
                ->timeout(10)
                ->get($endpoint);
        } catch (Throwable $exception) {
            throw CnpjLookupProviderException::failed(
                provider: $this->name(),
                message: 'ReceitaWS CNPJ lookup request failed.',
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
                message: sprintf('ReceitaWS CNPJ lookup failed with HTTP status %s.', $response->status()),
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
                message: 'ReceitaWS CNPJ lookup returned an invalid response.',
                httpStatus: $response->status(),
                context: [
                    'cnpj' => $cnpj->value(),
                    'endpoint' => $endpoint,
                ],
            );
        }

        if (($payload['status'] ?? 'OK') !== 'OK') {
            throw CnpjLookupProviderException::failed(
                provider: $this->name(),
                message: $this->string($payload, 'message') ?? 'ReceitaWS CNPJ lookup returned an error response.',
                httpStatus: $response->status(),
                context: [
                    'cnpj' => $cnpj->value(),
                    'endpoint' => $endpoint,
                    'response' => $payload,
                ],
            );
        }

        return new CnpjLookupResult(
            provider: $this->name(),
            cnpj: $cnpj->value(),
            legalName: $this->string($payload, 'nome'),
            tradeName: $this->string($payload, 'fantasia'),
            registrationStatusCode: null,
            registrationStatusName: $this->string($payload, 'situacao'),
            legalNatureCode: $this->leadingCode($this->string($payload, 'natureza_juridica')),
            legalNatureName: $this->string($payload, 'natureza_juridica'),
            companySizeCode: null,
            companySizeName: $this->string($payload, 'porte'),
            openedAt: $this->date($this->string($payload, 'abertura')),
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

    private function leadingCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $parts = explode(' - ', $value, 2);

        return trim($parts[0]) ?: null;
    }

    private function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('d/m/Y', $value);

        return $date === false ? $value : $date->format('Y-m-d');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizedPayload(array $payload, Cnpj $cnpj): array
    {
        $primaryActivity = $this->firstArrayItem($payload['atividade_principal'] ?? []);

        return [
            'cnpj' => $cnpj->value(),
            'cnpj_formatted' => $cnpj->formatted(),
            'cnpj_root' => $cnpj->root(),
            'cnpj_branch' => $cnpj->branch(),
            'cnpj_check_digits' => $cnpj->checkDigits(),

            'legal_name' => $this->string($payload, 'nome'),
            'trade_name' => $this->string($payload, 'fantasia'),

            'registration_status_code' => null,
            'registration_status_name' => $this->string($payload, 'situacao'),
            'registration_status_date' => $this->date($this->string($payload, 'data_situacao')),
            'registration_status_reason' => null,

            'legal_nature_code' => $this->leadingCode($this->string($payload, 'natureza_juridica')),
            'legal_nature_name' => $this->string($payload, 'natureza_juridica'),

            'company_size_code' => null,
            'company_size_name' => $this->string($payload, 'porte'),

            'opened_at' => $this->date($this->string($payload, 'abertura')),
            'share_capital' => $this->string($payload, 'capital_social'),

            'address' => [
                'postal_code' => $this->string($payload, 'cep'),
                'street' => $this->string($payload, 'logradouro'),
                'number' => $this->string($payload, 'numero'),
                'complement' => $this->string($payload, 'complemento'),
                'district' => $this->string($payload, 'bairro'),
                'city' => $this->string($payload, 'municipio'),
                'city_code' => null,
                'state' => $this->string($payload, 'uf'),
                'country_code' => 'BR',
            ],

            'contacts' => [
                'email' => $this->string($payload, 'email'),
                'phone_1' => $this->string($payload, 'telefone'),
                'phone_2' => null,
                'fax' => null,
            ],

            'cnae' => [
                'primary_code' => $primaryActivity['code'] ?? null,
                'primary_description' => $primaryActivity['text'] ?? null,
                'secondary' => $payload['atividades_secundarias'] ?? [],
            ],

            'members' => $payload['qsa'] ?? [],

            'tax_regime' => [
                'is_simples_nacional' => $this->nestedValue($payload, 'simples', 'optante'),
                'simples_nacional_opted_at' => $this->date($this->nestedString($payload, 'simples', 'data_opcao')),
                'simples_nacional_excluded_at' => $this->date($this->nestedString($payload, 'simples', 'data_exclusao')),
                'is_mei' => $this->nestedValue($payload, 'simei', 'optante'),
                'mei_opted_at' => $this->date($this->nestedString($payload, 'simei', 'data_opcao')),
                'mei_excluded_at' => $this->date($this->nestedString($payload, 'simei', 'data_exclusao')),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function firstArrayItem(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $first = reset($items);

        return is_array($first) ? $first : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function nestedString(array $payload, string $parent, string $key): ?string
    {
        if (! isset($payload[$parent]) || ! is_array($payload[$parent])) {
            return null;
        }

        return $this->string($payload[$parent], $key);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function nestedValue(array $payload, string $parent, string $key): mixed
    {
        if (! isset($payload[$parent]) || ! is_array($payload[$parent])) {
            return null;
        }

        return $payload[$parent][$key] ?? null;
    }
}
