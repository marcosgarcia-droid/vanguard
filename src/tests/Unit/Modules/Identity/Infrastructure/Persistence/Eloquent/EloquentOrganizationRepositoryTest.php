<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Domain\Organizations\Enums\OrganizationStatus;
use App\Modules\Identity\Domain\Organizations\Organization;
use App\Modules\Identity\Domain\Organizations\Repositories\OrganizationRepository;
use App\Modules\Identity\Domain\Organizations\ValueObjects\OrganizationId;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EloquentOrganizationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentOrganizationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_implements_the_organization_repository_contract(): void
    {
        $repository = new EloquentOrganizationRepository;

        $this->assertInstanceOf(OrganizationRepository::class, $repository);
    }

    public function test_it_saves_and_finds_an_organization_by_id(): void
    {
        $repository = new EloquentOrganizationRepository;

        $organization = Organization::create(
            id: new OrganizationId('org-001'),
            legalName: 'Agronorte Distribuidora',
            tradeName: 'Agronorte',
        );

        $organization->clearDomainEvents();

        $repository->save($organization);

        $this->assertDatabaseHas('organizations', [
            'id' => 'org-001',
            'legal_name' => 'Agronorte Distribuidora',
            'trade_name' => 'Agronorte',
            'status' => 'active',
        ]);

        $found = $repository->findById(new OrganizationId('org-001'));

        $this->assertInstanceOf(Organization::class, $found);
        $this->assertSame('org-001', $found->id()->value());
        $this->assertSame('Agronorte Distribuidora', $found->legalName());
        $this->assertSame('Agronorte', $found->tradeName());
        $this->assertSame(OrganizationStatus::Active, $found->status());
    }

    public function test_it_updates_an_existing_organization(): void
    {
        $repository = new EloquentOrganizationRepository;

        $organization = Organization::create(
            id: new OrganizationId('org-001'),
            legalName: 'Agronorte Distribuidora',
            tradeName: 'Agronorte',
        );

        $organization->clearDomainEvents();

        $repository->save($organization);

        $organization->rename(
            legalName: 'Agronorte Distribuidora LTDA',
            tradeName: 'Agronorte Matriz',
        );

        $organization->deactivate();

        $repository->save($organization);

        $this->assertDatabaseHas('organizations', [
            'id' => 'org-001',
            'legal_name' => 'Agronorte Distribuidora LTDA',
            'trade_name' => 'Agronorte Matriz',
            'status' => 'inactive',
        ]);

        $found = $repository->findById(new OrganizationId('org-001'));

        $this->assertSame('Agronorte Distribuidora LTDA', $found?->legalName());
        $this->assertSame('Agronorte Matriz', $found?->tradeName());
        $this->assertSame(OrganizationStatus::Inactive, $found?->status());
    }

    public function test_it_returns_null_when_organization_does_not_exist(): void
    {
        $repository = new EloquentOrganizationRepository;

        $this->assertNull($repository->findById(new OrganizationId('missing-org')));
    }
}
