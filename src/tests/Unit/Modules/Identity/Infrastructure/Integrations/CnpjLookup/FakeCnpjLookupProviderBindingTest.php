<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Integrations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Infrastructure\Integrations\CnpjLookup\FakeCnpjLookupProvider;
use Tests\TestCase;

class FakeCnpjLookupProviderBindingTest extends TestCase
{
    public function test_it_resolves_the_cnpj_lookup_provider_contract(): void
    {
        $provider = $this->app->make(CnpjLookupProvider::class);

        $this->assertInstanceOf(FakeCnpjLookupProvider::class, $provider);
    }
}
