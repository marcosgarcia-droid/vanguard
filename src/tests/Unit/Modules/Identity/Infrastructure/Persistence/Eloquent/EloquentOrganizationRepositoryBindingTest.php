<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Domain\Organizations\Repositories\OrganizationRepository;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EloquentOrganizationRepository;
use Tests\TestCase;

class EloquentOrganizationRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_organization_repository_contract(): void
    {
        $repository = $this->app->make(OrganizationRepository::class);

        $this->assertInstanceOf(EloquentOrganizationRepository::class, $repository);
    }
}
