<?php

namespace Tests\Unit\Modules\Identity\Domain\Organizations;

use App\Modules\Identity\Domain\Organizations\Enums\OrganizationStatus;
use App\Modules\Identity\Domain\Organizations\Events\OrganizationCreated;
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

    public function test_it_records_creation_event_when_created_through_factory(): void
    {
        $organization = Organization::create(
            id: new OrganizationId('org-001'),
            legalName: 'Agronorte Distribuidora',
            tradeName: 'Agronorte',
        );

        $events = $organization->releaseDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrganizationCreated::class, $events[0]);
        $this->assertSame('org-001', $events[0]->payload()['organization_id']);
        $this->assertSame('Agronorte Distribuidora', $events[0]->payload()['legal_name']);
        $this->assertSame('Agronorte', $events[0]->payload()['trade_name']);
        $this->assertSame('active', $events[0]->payload()['status']);

        $this->assertSame([], $organization->releaseDomainEvents());
    }
}
