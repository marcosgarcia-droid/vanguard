<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Integrations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupAttemptAwareProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProviderException;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Modules\Identity\Infrastructure\Integrations\CnpjLookup\FailoverCnpjLookupProvider;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FailoverCnpjLookupProviderTest extends TestCase
{
    public function test_it_implements_the_attempt_aware_cnpj_lookup_provider_contract(): void
    {
        $provider = new FailoverCnpjLookupProvider([
            $this->successfulProvider('primary-provider'),
        ]);

        $this->assertInstanceOf(CnpjLookupProvider::class, $provider);
        $this->assertInstanceOf(CnpjLookupAttemptAwareProvider::class, $provider);
        $this->assertSame('failover-cnpj', $provider->name());
        $this->assertSame([], $provider->attempts());
    }

    public function test_it_returns_the_first_successful_provider_result(): void
    {
        $primary = $this->successfulProvider('primary-provider');
        $secondary = $this->successfulProvider('secondary-provider');

        $provider = new FailoverCnpjLookupProvider([
            $primary,
            $secondary,
        ]);

        $result = $provider->lookup(new Cnpj('11.222.333/0001-81'));

        $this->assertSame('primary-provider', $result->provider);
        $this->assertSame(1, $primary->calls);
        $this->assertSame(0, $secondary->calls);

        $this->assertCount(1, $provider->attempts());
        $this->assertSame('primary-provider', $provider->attempts()[0]->provider);
        $this->assertTrue($provider->attempts()[0]->isSuccess());
        $this->assertSame($result, $provider->attempts()[0]->result);
    }

    public function test_it_falls_back_to_next_provider_when_first_provider_fails(): void
    {
        $primary = $this->failingProvider('primary-provider', 'Primary provider failed.', 503);
        $secondary = $this->successfulProvider('secondary-provider');

        $provider = new FailoverCnpjLookupProvider([
            $primary,
            $secondary,
        ]);

        $result = $provider->lookup(new Cnpj('11.222.333/0001-81'));

        $this->assertSame('secondary-provider', $result->provider);
        $this->assertSame(1, $primary->calls);
        $this->assertSame(1, $secondary->calls);

        $this->assertCount(2, $provider->attempts());

        $this->assertSame('primary-provider', $provider->attempts()[0]->provider);
        $this->assertTrue($provider->attempts()[0]->isFailure());
        $this->assertSame('Primary provider failed.', $provider->attempts()[0]->exception?->getMessage());
        $this->assertSame(503, $provider->attempts()[0]->context['http_status']);

        $this->assertSame('secondary-provider', $provider->attempts()[1]->provider);
        $this->assertTrue($provider->attempts()[1]->isSuccess());
        $this->assertSame($result, $provider->attempts()[1]->result);
    }

    public function test_it_throws_provider_exception_when_all_providers_fail(): void
    {
        $primary = $this->failingProvider('primary-provider', 'Primary provider failed.', 503);
        $secondary = $this->failingProvider('secondary-provider', 'Secondary provider failed.', 429);

        $provider = new FailoverCnpjLookupProvider([
            $primary,
            $secondary,
        ]);

        try {
            $provider->lookup(new Cnpj('11.222.333/0001-81'));

            $this->fail('Expected provider exception was not thrown.');
        } catch (CnpjLookupProviderException $exception) {
            $this->assertSame('failover-cnpj', $exception->provider());
            $this->assertSame('All CNPJ lookup providers failed.', $exception->getMessage());
            $this->assertSame('11222333000181', $exception->context()['cnpj']);

            $attempts = $exception->context()['attempts'];

            $this->assertCount(2, $attempts);
            $this->assertSame('primary-provider', $attempts[0]['provider']);
            $this->assertSame('Primary provider failed.', $attempts[0]['message']);
            $this->assertSame(503, $attempts[0]['http_status']);

            $this->assertSame('secondary-provider', $attempts[1]['provider']);
            $this->assertSame('Secondary provider failed.', $attempts[1]['message']);
            $this->assertSame(429, $attempts[1]['http_status']);
        }

        $this->assertSame(1, $primary->calls);
        $this->assertSame(1, $secondary->calls);

        $this->assertCount(2, $provider->attempts());
        $this->assertTrue($provider->attempts()[0]->isFailure());
        $this->assertTrue($provider->attempts()[1]->isFailure());
    }

    public function test_it_resets_attempts_between_lookups(): void
    {
        $provider = new FailoverCnpjLookupProvider([
            $this->successfulProvider('primary-provider'),
        ]);

        $provider->lookup(new Cnpj('11.222.333/0001-81'));
        $this->assertCount(1, $provider->attempts());

        $provider->lookup(new Cnpj('11.222.333/0001-81'));
        $this->assertCount(1, $provider->attempts());
    }

    public function test_it_requires_at_least_one_provider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one CNPJ lookup provider must be configured.');

        new FailoverCnpjLookupProvider([]);
    }

    private function successfulProvider(string $name): CnpjLookupProvider
    {
        return new class($name) implements CnpjLookupProvider
        {
            public int $calls = 0;

            public function __construct(
                private readonly string $name,
            ) {}

            public function name(): string
            {
                return $this->name;
            }

            public function lookup(Cnpj $cnpj): CnpjLookupResult
            {
                $this->calls++;

                return new CnpjLookupResult(
                    provider: $this->name(),
                    cnpj: $cnpj->value(),
                    legalName: 'Agronorte Distribuidora',
                    tradeName: 'Agronorte',
                    normalizedPayload: [
                        'cnpj' => $cnpj->value(),
                    ],
                    rawPayload: [
                        'provider' => $this->name(),
                    ],
                );
            }
        };
    }

    private function failingProvider(string $name, string $message, int $httpStatus): CnpjLookupProvider
    {
        return new class($name, $message, $httpStatus) implements CnpjLookupProvider
        {
            public int $calls = 0;

            public function __construct(
                private readonly string $name,
                private readonly string $message,
                private readonly int $httpStatus,
            ) {}

            public function name(): string
            {
                return $this->name;
            }

            public function lookup(Cnpj $cnpj): CnpjLookupResult
            {
                $this->calls++;

                throw CnpjLookupProviderException::failed(
                    provider: $this->name(),
                    message: $this->message,
                    httpStatus: $this->httpStatus,
                    context: [
                        'cnpj' => $cnpj->value(),
                    ],
                );
            }
        };
    }
}
