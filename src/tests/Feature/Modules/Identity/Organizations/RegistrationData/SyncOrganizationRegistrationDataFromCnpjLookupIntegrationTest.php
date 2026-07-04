<?php

namespace Tests\Feature\Modules\Identity\Organizations\RegistrationData;

use App\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup\SyncOrganizationRegistrationDataFromCnpjLookupCommand;
use App\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup\SyncOrganizationRegistrationDataFromCnpjLookupUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncOrganizationRegistrationDataFromCnpjLookupIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_registration_data_from_cnpj_lookup_into_the_organization_records(): void
    {
        config()->set('vanguard.integrations.cnpj_lookup.providers', [
            'receitaws',
        ]);

        DB::table('organizations')->insert([
            'id' => 'org-001',
            'status' => 'active',
            'legal_name' => 'Razão Social Antiga',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
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
                'qsa' => [
                    [
                        'nome' => 'Marcos Gustavo',
                        'qual' => 'Sócio-Administrador',
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

        $useCase = app(SyncOrganizationRegistrationDataFromCnpjLookupUseCase::class);

        $result = $useCase->execute(new SyncOrganizationRegistrationDataFromCnpjLookupCommand(
            organizationId: 'org-001',
            cnpj: '11.222.333/0001-81',
        ));

        $this->assertSame('receitaws', $result->provider);
        $this->assertSame('Agronorte Distribuidora', $result->legalName);
        $this->assertSame('Agronorte', $result->tradeName);

        $sync = DB::table('organization_cnpj_syncs')
            ->where('organization_id', 'org-001')
            ->where('cnpj', '11222333000181')
            ->first();

        $this->assertNotNull($sync);
        $this->assertSame('receitaws', $sync->provider);
        $this->assertSame('success', $sync->status);
        $this->assertNotNull($sync->response_hash);

        $organization = DB::table('organizations')->where('id', 'org-001')->first();

        $this->assertSame('11222333000181', $organization->cnpj);
        $this->assertSame('11.222.333/0001-81', $organization->cnpj_formatted);
        $this->assertSame('Agronorte Distribuidora', $organization->legal_name);
        $this->assertSame('Agronorte', $organization->trade_name);
        $this->assertSame('receitaws', $organization->cnpj_sync_provider);
        $this->assertNotNull($organization->cnpj_synced_at);

        $address = DB::table('organization_addresses')
            ->where('organization_id', 'org-001')
            ->where('source', 'cnpj_lookup')
            ->first();

        $this->assertNotNull($address);
        $this->assertSame('Rua Exemplo', $address->street);
        $this->assertSame('100', $address->number);
        $this->assertSame('Centro', $address->district);
        $this->assertSame('Ji-Paraná', $address->city);
        $this->assertSame('RO', $address->state);
        $this->assertTrue((bool) $address->is_primary);

        $contacts = DB::table('organization_contacts')
            ->where('organization_id', 'org-001')
            ->where('source', 'cnpj_lookup')
            ->orderBy('id')
            ->get();

        $this->assertGreaterThanOrEqual(2, $contacts->count());

        $this->assertTrue($contacts->contains(
            fn ($contact): bool => $contact->type === 'email'
                && $contact->value === 'contato@agronorte.test'
        ));

        $this->assertTrue($contacts->contains(
            fn ($contact): bool => $contact->type === 'phone'
                && $contact->normalized_value === '6934210000'
        ));

        $activity = DB::table('organization_cnae_activities')
            ->where('organization_id', 'org-001')
            ->where('source', 'cnpj_lookup')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('46.91-5-00', $activity->code);
        $this->assertSame('Comércio atacadista de mercadorias em geral', $activity->description);
        $this->assertTrue((bool) $activity->is_primary);

        $member = DB::table('organization_members')
            ->where('organization_id', 'org-001')
            ->where('source', 'cnpj_lookup')
            ->first();

        $this->assertNotNull($member);
        $this->assertSame('Marcos Gustavo', $member->name);

        $taxRegime = DB::table('organization_tax_regimes')
            ->where('organization_id', 'org-001')
            ->where('source', 'cnpj_lookup')
            ->first();

        $this->assertNotNull($taxRegime);
        $this->assertTrue((bool) $taxRegime->is_current);
        $this->assertTrue((bool) $taxRegime->is_simples_nacional);
        $this->assertFalse((bool) $taxRegime->is_mei);
        $this->assertNotNull($taxRegime->synced_at);

        Http::assertSentCount(1);
    }
}
