<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup;

use App\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup\SyncOrganizationRegistrationDataFromCnpjLookupUseCase;
use Tests\TestCase;

class SyncOrganizationRegistrationDataFromCnpjLookupUseCaseResolutionTest extends TestCase
{
    public function test_laravel_container_resolves_the_sync_organization_registration_data_from_cnpj_lookup_use_case(): void
    {
        $useCase = app(SyncOrganizationRegistrationDataFromCnpjLookupUseCase::class);

        $this->assertInstanceOf(SyncOrganizationRegistrationDataFromCnpjLookupUseCase::class, $useCase);
    }
}
