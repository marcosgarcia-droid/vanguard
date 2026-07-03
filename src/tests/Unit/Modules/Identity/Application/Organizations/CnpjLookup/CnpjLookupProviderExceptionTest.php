<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProviderException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CnpjLookupProviderExceptionTest extends TestCase
{
    public function test_it_represents_a_provider_failure(): void
    {
        $previous = new RuntimeException('HTTP client failed.');

        $exception = CnpjLookupProviderException::failed(
            provider: 'brasilapi',
            message: 'BrasilAPI CNPJ lookup failed.',
            httpStatus: 503,
            context: [
                'cnpj' => '11222333000181',
                'endpoint' => '/api/cnpj/v1/11222333000181',
            ],
            previous: $previous,
        );

        $this->assertSame('brasilapi', $exception->provider());
        $this->assertSame('BrasilAPI CNPJ lookup failed.', $exception->getMessage());
        $this->assertSame(503, $exception->httpStatus());
        $this->assertSame([
            'cnpj' => '11222333000181',
            'endpoint' => '/api/cnpj/v1/11222333000181',
        ], $exception->context());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_http_status_and_context_are_optional(): void
    {
        $exception = CnpjLookupProviderException::failed(
            provider: 'receitaws',
            message: 'ReceitaWS CNPJ lookup failed.',
        );

        $this->assertSame('receitaws', $exception->provider());
        $this->assertSame('ReceitaWS CNPJ lookup failed.', $exception->getMessage());
        $this->assertNull($exception->httpStatus());
        $this->assertSame([], $exception->context());
    }
}
