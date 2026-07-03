<?php

namespace App\Modules\Identity\Application\Organizations\CreateOrganization;

use App\Support\Contracts\Command;

final readonly class CreateOrganizationCommand implements Command
{
    public function __construct(
        public string $organizationId,
        public string $legalName,
        public ?string $tradeName = null,
        public ?string $cnpj = null,
    ) {}
}
