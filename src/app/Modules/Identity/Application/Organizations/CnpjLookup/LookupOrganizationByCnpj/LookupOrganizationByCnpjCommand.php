<?php

namespace App\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj;

use App\Support\Contracts\Command;

final readonly class LookupOrganizationByCnpjCommand implements Command
{
    public function __construct(
        public string $cnpj,
        public ?string $organizationId = null,
    ) {}
}
