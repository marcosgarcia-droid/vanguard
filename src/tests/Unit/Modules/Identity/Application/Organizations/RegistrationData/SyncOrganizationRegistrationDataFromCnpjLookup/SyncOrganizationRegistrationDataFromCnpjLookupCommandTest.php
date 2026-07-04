<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup;

use App\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup\SyncOrganizationRegistrationDataFromCnpjLookupCommand;
use PHPUnit\Framework\TestCase;

class SyncOrganizationRegistrationDataFromCnpjLookupCommandTest extends TestCase
{
    public function test_it_represents_the_intention_to_sync_registration_data_from_cnpj_lookup(): void
    {
        $command = new SyncOrganizationRegistrationDataFromCnpjLookupCommand(
            organizationId: 'org-001',
            cnpj: '11.222.333/0001-81',
        );

        $this->assertSame('org-001', $command->organizationId);
        $this->assertSame('11.222.333/0001-81', $command->cnpj);
    }
}
