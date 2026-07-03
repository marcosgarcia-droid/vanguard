<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\InMemory;

use App\Modules\Identity\Domain\Organizations\Repositories\OrganizationRepository;
use App\Modules\Identity\Infrastructure\Persistence\InMemory\InMemoryOrganizationRepository;
use Tests\TestCase;

class InMemoryOrganizationRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_organization_repository_contract(): void
    {
        $repository = $this->app->make(OrganizationRepository::class);

        $this->assertInstanceOf(InMemoryOrganizationRepository::class, $repository);
    }
}
