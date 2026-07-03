<?php

namespace Tests\Unit\Modules\Identity\Domain\Organizations;

use App\Modules\Identity\Domain\Organizations\Enums\OrganizationStatus;
use App\Modules\Identity\Domain\Organizations\Organization;
use App\Modules\Identity\Domain\Organizations\ValueObjects\OrganizationId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OrganizationTest extends TestCase
{
    public function test_it_creates_an_active_organization(): void
    {
        $organization = new Organization(
            id: new OrganizationId('org-001'),
            legalName: 'Agronorte Distribuidora',
            tradeName: 'Agronorte',
        );

        $this->assertSame('org-001', $organization->id()->value());
        $this->assertSame('Agronorte Distribuidora', $organization->legalName());
        $this->assertSame('Agronorte', $organization->tradeName());
        $this->assertSame(OrganizationStatus::Active, $organization->status());
        $this->assertTrue($organization->isActive());
    }

    public function test_it_can_be_deactivated_and_activated(): void
    {
        $organization = new Organization(
            id: new OrganizationId('org-001'),
            legalName: 'Agronorte Distribuidora',
        );

        $organization->deactivate();

        $this->assertSame(OrganizationStatus::Inactive, $organization->status());
        $this->assertFalse($organization->isActive());

        $organization->activate();

        $this->assertSame(OrganizationStatus::Active, $organization->status());
        $this->assertTrue($organization->isActive());
    }

    public function test_it_does_not_accept_empty_legal_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Organization(
            id: new OrganizationId('org-001'),
            legalName: '',
        );
    }

    public function test_it_does_not_accept_empty_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OrganizationId('');
    }
}
