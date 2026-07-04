<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Integrations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupAttemptAwareProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj\LookupOrganizationByCnpjUseCase;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Modules\Identity\Infrastructure\Integrations\CnpjLookup\FailoverCnpjLookupProvider;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
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

    public function test_it_honors_configured_cnpj_lookup_provider_order(): void
    {
        config()->set('vanguard.integrations.cnpj_lookup.providers', [
            'receitaws',
            'brasilapi',
        ]);

        Http::fake([
            'https://www.receitaws.com.br/v1/cnpj/11222333000181' => Http::response([
                'status' => 'OK',
                'cnpj' => '11.222.333/0001-81',
                'nome' => 'Agronorte Distribuidora',
                'fantasia' => 'Agronorte',
                'email' => 'contato@agronorte.test',
            ], 200),
        ]);

        $provider = app(CnpjLookupProvider::class);

        $result = $provider->lookup(new Cnpj('11.222.333/0001-81'));

        $this->assertSame('receitaws', $result->provider);
        $this->assertSame('Agronorte Distribuidora', $result->legalName);
        $this->assertSame('Agronorte', $result->tradeName);

        Http::assertSentCount(1);

        Http::assertSent(
            fn ($request): bool => $request->url() === 'https://www.receitaws.com.br/v1/cnpj/11222333000181',
        );

        Http::assertNotSent(
            fn ($request): bool => str_contains($request->url(), 'brasilapi.com.br'),
        );
    }

    public function test_it_honors_configured_brasilapi_base_url(): void
    {
        config()->set('vanguard.integrations.cnpj_lookup.providers', [
            'brasilapi',
        ]);

        config()->set('vanguard.integrations.cnpj_lookup.brasilapi.base_url', 'https://brasilapi.test');

        Http::fake([
            'https://brasilapi.test/api/cnpj/v1/11222333000181' => Http::response([
                'cnpj' => '11222333000181',
                'razao_social' => 'Agronorte Distribuidora',
                'nome_fantasia' => 'Agronorte',
            ], 200),
        ]);

        $provider = app(CnpjLookupProvider::class);

        $result = $provider->lookup(new Cnpj('11.222.333/0001-81'));

        $this->assertSame('brasilapi', $result->provider);
        $this->assertSame('Agronorte Distribuidora', $result->legalName);
        $this->assertSame('Agronorte', $result->tradeName);

        Http::assertSentCount(1);

        Http::assertSent(
            fn ($request): bool => $request->url() === 'https://brasilapi.test/api/cnpj/v1/11222333000181',
        );
    }

    public function test_it_honors_configured_receitaws_base_url(): void
    {
        config()->set('vanguard.integrations.cnpj_lookup.providers', [
            'receitaws',
        ]);

        config()->set('vanguard.integrations.cnpj_lookup.receitaws.base_url', 'https://receitaws.test');

        Http::fake([
            'https://receitaws.test/v1/cnpj/11222333000181' => Http::response([
                'status' => 'OK',
                'cnpj' => '11.222.333/0001-81',
                'nome' => 'Agronorte Distribuidora',
                'fantasia' => 'Agronorte',
            ], 200),
        ]);

        $provider = app(CnpjLookupProvider::class);

        $result = $provider->lookup(new Cnpj('11.222.333/0001-81'));

        $this->assertSame('receitaws', $result->provider);
        $this->assertSame('Agronorte Distribuidora', $result->legalName);
        $this->assertSame('Agronorte', $result->tradeName);

        Http::assertSentCount(1);

        Http::assertSent(
            fn ($request): bool => $request->url() === 'https://receitaws.test/v1/cnpj/11222333000181',
        );
    }

    public function test_it_rejects_unsupported_cnpj_lookup_provider_configuration(): void
    {
        config()->set('vanguard.integrations.cnpj_lookup.providers', [
            'unknown-provider',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported CNPJ lookup provider [unknown-provider].');

        app(CnpjLookupProvider::class);
    }
}
