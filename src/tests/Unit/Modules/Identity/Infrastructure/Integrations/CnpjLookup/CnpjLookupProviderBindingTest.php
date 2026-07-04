<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Integrations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupAttemptAwareProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj\LookupOrganizationByCnpjUseCase;
use App\Modules\Identity\Infrastructure\Integrations\CnpjLookup\FailoverCnpjLookupProvider;
use Tests\TestCase;

class CnpjLookupProviderBindingTest extends TestCase
{
    public function test_it_resolves_the_cnpj_lookup_provider_as_failover_provider(): void
    {
        $provider = app(CnpjLookupProvider::class);

        $this->assertInstanceOf(CnpjLookupProvider::class, $provider);
        $this->assertInstanceOf(CnpjLookupAttemptAwareProvider::class, $provider);
        $this->assertInstanceOf(FailoverCnpjLookupProvider::class, $provider);
        $this->assertSame('failover-cnpj', $provider->name());
    }

    public function test_it_resolves_the_lookup_organization_by_cnpj_use_case(): void
    {
        $useCase = app(LookupOrganizationByCnpjUseCase::class);

        $this->assertInstanceOf(LookupOrganizationByCnpjUseCase::class, $useCase);
    }
}
