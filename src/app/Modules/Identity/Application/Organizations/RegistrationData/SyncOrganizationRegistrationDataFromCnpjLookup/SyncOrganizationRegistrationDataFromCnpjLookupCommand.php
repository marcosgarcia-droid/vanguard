<?php

namespace App\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup;

use App\Support\Contracts\Command;

final readonly class SyncOrganizationRegistrationDataFromCnpjLookupCommand implements Command
{
    public function __construct(
        public string $organizationId,
        public string $cnpj,
    ) {}
}
