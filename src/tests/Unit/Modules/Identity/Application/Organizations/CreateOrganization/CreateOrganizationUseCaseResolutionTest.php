<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\CreateOrganization;

use App\Modules\Identity\Application\Organizations\CreateOrganization\CreateOrganizationUseCase;
use Tests\TestCase;

class CreateOrganizationUseCaseResolutionTest extends TestCase
{
    public function test_laravel_container_resolves_the_create_organization_use_case(): void
    {
        $useCase = $this->app->make(CreateOrganizationUseCase::class);

        $this->assertInstanceOf(CreateOrganizationUseCase::class, $useCase);
    }
}
