<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupAttempt;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProviderException;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSyncStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CnpjLookupAttemptTest extends TestCase
{
    public function test_it_represents_a_successful_provider_attempt(): void
    {
        $requestedAt = new DateTimeImmutable('2026-01-01 10:00:00');
        $respondedAt = new DateTimeImmutable('2026-01-01 10:00:01');

        $result = new CnpjLookupResult(
            provider: 'brasilapi',
            cnpj: '11222333000181',
            legalName: 'Agronorte Distribuidora',
            normalizedPayload: [
                'legal_name' => 'Agronorte Distribuidora',
            ],
            rawPayload: [
                'razao_social' => 'Agronorte Distribuidora',
            ],
        );

        $attempt = CnpjLookupAttempt::success(
            provider: 'brasilapi',
            result: $result,
            requestedAt: $requestedAt,
            respondedAt: $respondedAt,
            durationMs: 100,
        );

        $this->assertSame('brasilapi', $attempt->provider);
        $this->assertSame(CnpjLookupSyncStatus::Success, $attempt->status);
        $this->assertSame($requestedAt, $attempt->requestedAt);
        $this->assertSame($respondedAt, $attempt->respondedAt);
        $this->assertSame(100, $attempt->durationMs);
        $this->assertSame($result, $attempt->result);
        $this->assertNull($attempt->exception);
        $this->assertSame([], $attempt->context);
        $this->assertTrue($attempt->isSuccess());
        $this->assertFalse($attempt->isFailure());
    }

    public function test_it_represents_a_failed_provider_attempt(): void
    {
        $requestedAt = new DateTimeImmutable('2026-01-01 10:00:00');
        $respondedAt = new DateTimeImmutable('2026-01-01 10:00:01');

        $exception = CnpjLookupProviderException::failed(
            provider: 'brasilapi',
            message: 'BrasilAPI failed.',
            httpStatus: 503,
            context: [
                'endpoint' => '/api/cnpj/v1/11222333000181',
            ],
        );

        $attempt = CnpjLookupAttempt::failed(
            provider: 'brasilapi',
            exception: $exception,
            requestedAt: $requestedAt,
            respondedAt: $respondedAt,
            durationMs: 100,
            context: [
                'http_status' => 503,
            ],
        );

        $this->assertSame('brasilapi', $attempt->provider);
        $this->assertSame(CnpjLookupSyncStatus::Failed, $attempt->status);
        $this->assertSame($requestedAt, $attempt->requestedAt);
        $this->assertSame($respondedAt, $attempt->respondedAt);
        $this->assertSame(100, $attempt->durationMs);
        $this->assertNull($attempt->result);
        $this->assertSame($exception, $attempt->exception);
        $this->assertSame(['http_status' => 503], $attempt->context);
        $this->assertFalse($attempt->isSuccess());
        $this->assertTrue($attempt->isFailure());
    }
}
