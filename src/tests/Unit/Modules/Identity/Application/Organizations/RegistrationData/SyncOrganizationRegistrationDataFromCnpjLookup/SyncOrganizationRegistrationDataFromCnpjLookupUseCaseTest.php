<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSync;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSyncRepository;
use App\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj\LookupOrganizationByCnpjUseCase;
use App\Modules\Identity\Application\Organizations\RegistrationData\OrganizationRegistrationDataRepository;
use App\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup\SyncOrganizationRegistrationDataFromCnpjLookupCommand;
use App\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup\SyncOrganizationRegistrationDataFromCnpjLookupUseCase;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Support\Contracts\TransactionManager;
use PHPUnit\Framework\TestCase;

class SyncOrganizationRegistrationDataFromCnpjLookupUseCaseTest extends TestCase
{
    public function test_it_looks_up_cnpj_and_applies_registration_data_to_the_organization(): void
    {
        $provider = new class implements CnpjLookupProvider
        {
            public function name(): string
            {
                return 'receitaws';
            }

            public function lookup(Cnpj $cnpj): CnpjLookupResult
            {
                return new CnpjLookupResult(
                    provider: 'receitaws',
                    cnpj: $cnpj,
                    legalName: 'Agronorte Distribuidora',
                    tradeName: 'Agronorte',
                    normalizedPayload: [
                        'legal_name' => 'Agronorte Distribuidora',
                        'trade_name' => 'Agronorte',
                    ],
                    rawPayload: [
                        'nome' => 'Agronorte Distribuidora',
                        'fantasia' => 'Agronorte',
                    ],
                );
            }
        };

        $syncs = new class implements CnpjLookupSyncRepository
        {
            /**
             * @var list<CnpjLookupSync>
             */
            public array $items = [];

            public function save(CnpjLookupSync $sync): void
            {
                $this->items[] = $sync;
            }
        };

        $transactions = new class implements TransactionManager
        {
            public int $runs = 0;

            public function run(callable $callback): mixed
            {
                $this->runs++;

                return $callback();
            }
        };

        $registrationData = new class implements OrganizationRegistrationDataRepository
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

        $lookupUseCase = new LookupOrganizationByCnpjUseCase(
            provider: $provider,
            syncs: $syncs,
            transactions: $transactions,
        );

        $useCase = new SyncOrganizationRegistrationDataFromCnpjLookupUseCase(
            lookupOrganizationByCnpj: $lookupUseCase,
            registrationData: $registrationData,
            transactions: $transactions,
        );

        $result = $useCase->execute(new SyncOrganizationRegistrationDataFromCnpjLookupCommand(
            organizationId: 'org-001',
            cnpj: '11.222.333/0001-81',
        ));

        $this->assertSame('receitaws', $result->provider);
        $this->assertSame('Agronorte Distribuidora', $result->legalName);
        $this->assertCount(1, $syncs->items);
        $this->assertSame('org-001', $syncs->items[0]->organizationId);

        $this->assertSame('org-001', $registrationData->organizationId);
        $this->assertSame('11222333000181', $registrationData->cnpj?->value());
        $this->assertSame('receitaws', $registrationData->provider);
        $this->assertSame('Agronorte Distribuidora', $registrationData->normalizedPayload['legal_name']);
        $this->assertSame('Agronorte', $registrationData->normalizedPayload['trade_name']);

        $this->assertSame(2, $transactions->runs);
    }
}
