<?php

namespace App\Modules\Identity\Application\Organizations\RegistrationData;

use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;

interface OrganizationRegistrationDataRepository
{
    /**
     * @param  array<string, mixed>  $normalizedPayload
     */
    public function applyFromCnpjLookup(
        string $organizationId,
        Cnpj $cnpj,
        string $provider,
        array $normalizedPayload,
    ): void;
}
