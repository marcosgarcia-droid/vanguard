<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Integrations\CnpjLookup;

use Tests\TestCase;

class CnpjLookupConfigurationTest extends TestCase
{
    public function test_it_defines_default_cnpj_lookup_provider_order(): void
    {
        $this->assertSame([
            'brasilapi',
            'receitaws',
        ], config('vanguard.integrations.cnpj_lookup.providers'));
    }

    public function test_it_defines_default_cnpj_lookup_provider_base_urls(): void
    {
        $this->assertSame(
            'https://brasilapi.com.br',
            config('vanguard.integrations.cnpj_lookup.brasilapi.base_url'),
        );

        $this->assertSame(
            'https://www.receitaws.com.br',
            config('vanguard.integrations.cnpj_lookup.receitaws.base_url'),
        );
    }
}
