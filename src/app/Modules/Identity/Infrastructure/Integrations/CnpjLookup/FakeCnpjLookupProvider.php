<?php

namespace App\Modules\Identity\Infrastructure\Integrations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;

final class FakeCnpjLookupProvider implements CnpjLookupProvider
{
    public function lookup(Cnpj $cnpj): CnpjLookupResult
    {
        return new CnpjLookupResult(
            provider: 'fake-cnpj',
            cnpj: $cnpj->value(),
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
                'cnpj' => $cnpj->value(),
                'cnpj_formatted' => $cnpj->formatted(),
                'cnpj_root' => $cnpj->root(),
                'cnpj_branch' => $cnpj->branch(),
                'cnpj_check_digits' => $cnpj->checkDigits(),
                'legal_name' => 'Agronorte Distribuidora',
                'trade_name' => 'Agronorte',
                'registration_status_code' => '02',
                'registration_status_name' => 'Ativa',
                'legal_nature_code' => '2062',
                'legal_nature_name' => 'Sociedade Empresária Limitada',
                'company_size_code' => '05',
                'company_size_name' => 'Demais',
                'opened_at' => '2020-01-01',
                'share_capital' => '100000.00',
            ],
            rawPayload: [
                'provider' => 'fake-cnpj',
                'cnpj' => $cnpj->formatted(),
                'razao_social' => 'Agronorte Distribuidora',
                'nome_fantasia' => 'Agronorte',
                'situacao_cadastral' => 'Ativa',
            ],
        );
    }
}
