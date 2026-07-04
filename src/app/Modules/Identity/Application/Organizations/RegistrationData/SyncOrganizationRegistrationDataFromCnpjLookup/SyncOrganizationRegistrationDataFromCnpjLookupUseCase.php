<?php

namespace App\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj\LookupOrganizationByCnpjCommand;
use App\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj\LookupOrganizationByCnpjUseCase;
use App\Modules\Identity\Application\Organizations\RegistrationData\OrganizationRegistrationDataRepository;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Support\Contracts\TransactionManager;
use App\Support\Contracts\UseCase;

final readonly class SyncOrganizationRegistrationDataFromCnpjLookupUseCase implements UseCase
{
    public function __construct(
        private LookupOrganizationByCnpjUseCase $lookupOrganizationByCnpj,
        private OrganizationRegistrationDataRepository $registrationData,
        private TransactionManager $transactions,
    ) {}

    public function execute(SyncOrganizationRegistrationDataFromCnpjLookupCommand $command): CnpjLookupResult
    {
        $result = $this->lookupOrganizationByCnpj->execute(new LookupOrganizationByCnpjCommand(
            cnpj: $command->cnpj,
            organizationId: $command->organizationId,
        ));

        $this->transactions->run(function () use ($command, $result): void {
            $this->registrationData->applyFromCnpjLookup(
                organizationId: $command->organizationId,
                cnpj: $result->cnpj instanceof Cnpj ? $result->cnpj : new Cnpj($result->cnpj),
                provider: $result->provider,
                normalizedPayload: $result->normalizedPayload,
            );
        });

        return $result;
    }
}
