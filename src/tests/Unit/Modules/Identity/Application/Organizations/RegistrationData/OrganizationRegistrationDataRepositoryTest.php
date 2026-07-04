<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\RegistrationData;

use App\Modules\Identity\Application\Organizations\RegistrationData\OrganizationRegistrationDataRepository;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use PHPUnit\Framework\TestCase;

class OrganizationRegistrationDataRepositoryTest extends TestCase
{
    public function test_it_defines_a_contract_for_applying_cnpj_lookup_data_to_an_organization(): void
    {
        $repository = new class implements OrganizationRegistrationDataRepository
        {
            public ?string $organizationId = null;

            public ?Cnpj $cnpj = null;

            public ?string $provider = null;

            /**
             * @var array<string, mixed>
             */
            public array $normalizedPayload = [];

            public function applyFromCnpjLookup(
                string $organizationId,
                Cnpj $cnpj,
                string $provider,
                array $normalizedPayload,
            ): void {
                $this->organizationId = $organizationId;
                $this->cnpj = $cnpj;
                $this->provider = $provider;
                $this->normalizedPayload = $normalizedPayload;
            }
        };

        $repository->applyFromCnpjLookup(
            organizationId: 'org-001',
            cnpj: new Cnpj('11.222.333/0001-81'),
            provider: 'receitaws',
            normalizedPayload: [
                'legal_name' => 'Agronorte Distribuidora',
                'trade_name' => 'Agronorte',
                'address' => [
                    'city' => 'Ji-Paraná',
                    'state' => 'RO',
                ],
            ],
        );

        $this->assertSame('org-001', $repository->organizationId);
        $this->assertSame('11222333000181', $repository->cnpj?->value());
        $this->assertSame('receitaws', $repository->provider);
        $this->assertSame('Agronorte Distribuidora', $repository->normalizedPayload['legal_name']);
        $this->assertSame('Agronorte', $repository->normalizedPayload['trade_name']);
        $this->assertSame('Ji-Paraná', $repository->normalizedPayload['address']['city']);
        $this->assertSame('RO', $repository->normalizedPayload['address']['state']);
    }
}
