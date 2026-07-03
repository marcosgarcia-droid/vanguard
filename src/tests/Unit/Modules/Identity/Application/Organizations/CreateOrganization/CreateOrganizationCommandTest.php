<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\CreateOrganization;

use App\Modules\Identity\Application\Organizations\CreateOrganization\CreateOrganizationCommand;
use App\Support\Contracts\Command;
use PHPUnit\Framework\TestCase;

class CreateOrganizationCommandTest extends TestCase
{
    public function test_it_represents_the_intention_to_create_an_organization(): void
    {
        $command = new CreateOrganizationCommand(
            organizationId: 'org-001',
            legalName: 'Agronorte Distribuidora',
            tradeName: 'Agronorte',
        );

        $this->assertInstanceOf(Command::class, $command);
        $this->assertSame('org-001', $command->organizationId);
        $this->assertSame('Agronorte Distribuidora', $command->legalName);
        $this->assertSame('Agronorte', $command->tradeName);
    }

    public function test_trade_name_is_optional(): void
    {
        $command = new CreateOrganizationCommand(
            organizationId: 'org-001',
            legalName: 'Agronorte Distribuidora',
        );

        $this->assertNull($command->tradeName);
    }
}
