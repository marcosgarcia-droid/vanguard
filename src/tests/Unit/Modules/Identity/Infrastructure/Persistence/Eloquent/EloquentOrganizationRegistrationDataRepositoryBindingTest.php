<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Application\Organizations\RegistrationData\OrganizationRegistrationDataRepository;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EloquentOrganizationRegistrationDataRepository;
use Tests\TestCase;

class EloquentOrganizationRegistrationDataRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_organization_registration_data_repository_contract(): void
    {
        $repository = app(OrganizationRegistrationDataRepository::class);

        $this->assertInstanceOf(EloquentOrganizationRegistrationDataRepository::class, $repository);
    }
}
