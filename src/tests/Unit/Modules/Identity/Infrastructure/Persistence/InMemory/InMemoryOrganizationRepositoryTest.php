<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\InMemory;

use App\Modules\Identity\Domain\Organizations\Organization;
use App\Modules\Identity\Domain\Organizations\Repositories\OrganizationRepository;
use App\Modules\Identity\Domain\Organizations\ValueObjects\OrganizationId;
use App\Modules\Identity\Infrastructure\Persistence\InMemory\InMemoryOrganizationRepository;
use PHPUnit\Framework\TestCase;

class InMemoryOrganizationRepositoryTest extends TestCase
{
    public function test_it_implements_the_organization_repository_contract(): void
    {
        $repository = new InMemoryOrganizationRepository;

        $this->assertInstanceOf(OrganizationRepository::class, $repository);
    }

    public function test_it_saves_and_finds_an_organization_by_id(): void
    {
        $repository = new InMemoryOrganizationRepository;

        $organization = Organization::create(
            id: new OrganizationId('org-001'),
            legalName: 'Agronorte Distribuidora',
            tradeName: 'Agronorte',
        );

        $organization->clearDomainEvents();

        $repository->save($organization);

        $found = $repository->findById(new OrganizationId('org-001'));

        $this->assertSame($organization, $found);
        $this->assertSame('Agronorte Distribuidora', $found?->legalName());
    }

    public function test_it_returns_null_when_organization_does_not_exist(): void
    {
        $repository = new InMemoryOrganizationRepository;

        $this->assertNull($repository->findById(new OrganizationId('missing-org')));
    }
}
