<?php

namespace App\Modules\Identity\Application\Organizations\CnpjLookup;

final readonly class CnpjLookupResult
{
    public function __construct(
        public string $provider,
        public string $cnpj,
        public ?string $legalName = null,
        public ?string $tradeName = null,
        public ?string $registrationStatusCode = null,
        public ?string $registrationStatusName = null,
        public ?string $legalNatureCode = null,
        public ?string $legalNatureName = null,
        public ?string $companySizeCode = null,
        public ?string $companySizeName = null,
        public ?string $openedAt = null,
        public ?string $shareCapital = null,
        public array $normalizedPayload = [],
        public array $rawPayload = [],
    ) {}
}
