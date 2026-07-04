<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Application\Organizations\RegistrationData\OrganizationRegistrationDataRepository;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EloquentOrganizationRegistrationDataRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use JsonException;
use Tests\TestCase;

class EloquentOrganizationRegistrationDataRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_implements_the_organization_registration_data_repository_contract(): void
    {
        $repository = new EloquentOrganizationRegistrationDataRepository;

        $this->assertInstanceOf(OrganizationRegistrationDataRepository::class, $repository);
    }

    /**
     * @throws JsonException
     */
    public function test_it_applies_cnpj_lookup_data_to_an_existing_organization(): void
    {
        DB::table('organizations')->insert([
            'id' => 'org-001',
            'status' => 'active',
            'legal_name' => 'Razão Social Antiga',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $repository = new EloquentOrganizationRegistrationDataRepository;

        $repository->applyFromCnpjLookup(
            organizationId: 'org-001',
            cnpj: new Cnpj('11.222.333/0001-81'),
            provider: 'receitaws',
            normalizedPayload: [
                'legal_name' => 'Agronorte Distribuidora',
                'trade_name' => 'Agronorte',
                'opened_at' => '2020-01-01',
                'legal_nature_code' => '2062',
                'legal_nature_name' => 'Sociedade Empresária Limitada',
                'company_size_code' => '05',
                'company_size_name' => 'DEMAIS',
                'share_capital' => '100000.00',
                'registration_status_code' => '02',
                'registration_status_name' => 'ATIVA',
                'registration_status_date' => '2020-01-01',
                'address' => [
                    'postal_code' => '76900000',
                    'street' => 'Rua Exemplo',
                    'number' => '100',
                    'district' => 'Centro',
                    'city' => 'Ji-Paraná',
                    'state' => 'RO',
                ],
                'contacts' => [
                    [
                        'type' => 'email',
                        'value' => 'contato@agronorte.test',
                    ],
                    [
                        'type' => 'phone',
                        'value' => '(69) 3421-0000',
                    ],
                ],
                'cnae' => [
                    [
                        'code' => '4691500',
                        'description' => 'Comércio atacadista de mercadorias em geral',
                        'is_primary' => true,
                    ],
                    [
                        'code' => '4623109',
                        'description' => 'Comércio atacadista de alimentos',
                        'is_primary' => false,
                    ],
                ],
                'members' => [
                    [
                        'name' => 'Marcos Gustavo',
                        'document_type' => 'cpf',
                        'document_number' => '***',
                        'qualification_code' => '49',
                        'qualification_name' => 'Sócio-Administrador',
                        'role' => 'Administrador',
                        'is_legal_representative' => true,
                    ],
                ],
                'tax_regime' => [
                    'is_simples_nacional' => true,
                    'simples_nacional_opted_at' => '2020-01-01',
                    'is_mei' => false,
                    'tax_regime' => 'simples_nacional',
                ],
            ],
        );

        $organization = DB::table('organizations')->where('id', 'org-001')->first();

        $this->assertSame('11222333000181', $organization->cnpj);
        $this->assertSame('11.222.333/0001-81', $organization->cnpj_formatted);
        $this->assertSame('11222333', $organization->cnpj_root);
        $this->assertSame('0001', $organization->cnpj_branch);
        $this->assertSame('81', $organization->cnpj_check_digits);
        $this->assertSame('Agronorte Distribuidora', $organization->legal_name);
        $this->assertSame('Agronorte', $organization->trade_name);
        $this->assertSame('2020-01-01', $organization->opened_at);
        $this->assertSame('2062', $organization->legal_nature_code);
        $this->assertSame('Sociedade Empresária Limitada', $organization->legal_nature_name);
        $this->assertSame('05', $organization->company_size_code);
        $this->assertSame('DEMAIS', $organization->company_size_name);
        $this->assertSame('100000.00', number_format((float) $organization->share_capital, 2, '.', ''));
        $this->assertSame('02', $organization->tax_registration_status_code);
        $this->assertSame('ATIVA', $organization->tax_registration_status_name);
        $this->assertSame('2020-01-01', $organization->tax_registration_status_date);
        $this->assertSame('receitaws', $organization->cnpj_sync_provider);
        $this->assertNotNull($organization->cnpj_synced_at);

        $normalizedData = json_decode(
            json: $organization->cnpj_normalized_data,
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('Agronorte Distribuidora', $normalizedData['legal_name']);
        $this->assertSame('Agronorte', $normalizedData['trade_name']);

        $address = DB::table('organization_addresses')->where('organization_id', 'org-001')->first();

        $this->assertSame('main', $address->type);
        $this->assertSame('76900000', $address->postal_code);
        $this->assertSame('Rua Exemplo', $address->street);
        $this->assertSame('100', $address->number);
        $this->assertSame('Centro', $address->district);
        $this->assertSame('Ji-Paraná', $address->city);
        $this->assertSame('RO', $address->state);
        $this->assertTrue((bool) $address->is_primary);
        $this->assertSame('cnpj_lookup', $address->source);

        $contacts = DB::table('organization_contacts')
            ->where('organization_id', 'org-001')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $contacts);
        $this->assertSame('email', $contacts[0]->type);
        $this->assertSame('contato@agronorte.test', $contacts[0]->value);
        $this->assertSame('contato@agronorte.test', $contacts[0]->normalized_value);
        $this->assertSame('phone', $contacts[1]->type);
        $this->assertSame('(69) 3421-0000', $contacts[1]->value);
        $this->assertSame('6934210000', $contacts[1]->normalized_value);

        $activities = DB::table('organization_cnae_activities')
            ->where('organization_id', 'org-001')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $activities);
        $this->assertSame('4691500', $activities[0]->code);
        $this->assertTrue((bool) $activities[0]->is_primary);
        $this->assertSame('4623109', $activities[1]->code);
        $this->assertFalse((bool) $activities[1]->is_primary);

        $member = DB::table('organization_members')->where('organization_id', 'org-001')->first();

        $this->assertSame('Marcos Gustavo', $member->name);
        $this->assertSame('cpf', $member->document_type);
        $this->assertSame('***', $member->document_number);
        $this->assertSame('49', $member->qualification_code);
        $this->assertSame('Sócio-Administrador', $member->qualification_name);
        $this->assertSame('Administrador', $member->role);
        $this->assertTrue((bool) $member->is_legal_representative);
        $this->assertSame('cnpj_lookup', $member->source);

        $taxRegime = DB::table('organization_tax_regimes')->where('organization_id', 'org-001')->first();

        $this->assertTrue((bool) $taxRegime->is_current);
        $this->assertTrue((bool) $taxRegime->is_simples_nacional);
        $this->assertSame('2020-01-01', $taxRegime->simples_nacional_opted_at);
        $this->assertFalse((bool) $taxRegime->is_mei);
        $this->assertSame('simples_nacional', $taxRegime->tax_regime);
        $this->assertSame('cnpj_lookup', $taxRegime->source);
        $this->assertNotNull($taxRegime->synced_at);
    }
}
