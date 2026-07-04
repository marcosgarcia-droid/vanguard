<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupAttempt;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupAttemptAwareProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CnpjLookupAttemptAwareProviderTest extends TestCase
{
    public function test_it_defines_a_contract_for_providers_that_expose_attempts(): void
    {
        $provider = new class implements CnpjLookupAttemptAwareProvider
        {
            /**
             * @var list<CnpjLookupAttempt>
             */
            private array $attempts = [];

            public function name(): string
            {
                return 'attempt-aware-provider';
            }

            public function lookup(Cnpj $cnpj): CnpjLookupResult
            {
                $result = new CnpjLookupResult(
                    provider: $this->name(),
                    cnpj: $cnpj->value(),
                    legalName: 'Agronorte Distribuidora',
                );

                $now = new DateTimeImmutable;

                $this->attempts[] = CnpjLookupAttempt::success(
                    provider: $this->name(),
                    result: $result,
                    requestedAt: $now,
                    respondedAt: $now,
                    durationMs: 0,
                );

                return $result;
            }

            public function attempts(): array
            {
                return $this->attempts;
            }
        };

        $this->assertInstanceOf(CnpjLookupProvider::class, $provider);
        $this->assertInstanceOf(CnpjLookupAttemptAwareProvider::class, $provider);

        $result = $provider->lookup(new Cnpj('11.222.333/0001-81'));

        $this->assertSame('attempt-aware-provider', $result->provider);
        $this->assertCount(1, $provider->attempts());
        $this->assertSame('attempt-aware-provider', $provider->attempts()[0]->provider);
    }
}
