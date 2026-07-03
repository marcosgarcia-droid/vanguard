<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\CnpjLookup;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSync;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSyncStatus;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CnpjLookupSyncTest extends TestCase
{
    public function test_it_represents_a_successful_cnpj_lookup_sync(): void
    {
        $requestedAt = new DateTimeImmutable('2026-01-01 10:00:00');
        $respondedAt = new DateTimeImmutable('2026-01-01 10:00:01');

        $sync = CnpjLookupSync::success(
            cnpj: new Cnpj('11.222.333/0001-81'),
            provider: 'fake-provider',
            responsePayload: ['razao_social' => 'Agronorte Distribuidora'],
            normalizedPayload: ['legal_name' => 'Agronorte Distribuidora'],
            organizationId: 'org-001',
            endpoint: 'https://example.test/cnpj/11222333000181',
            httpStatus: 200,
            requestedAt: $requestedAt,
            respondedAt: $respondedAt,
            durationMs: 1000,
            requestPayload: ['cnpj' => '11222333000181'],
            responseHash: 'abc123',
        );

        $this->assertSame('11222333000181', $sync->cnpj->value());
        $this->assertSame('fake-provider', $sync->provider);
        $this->assertSame(CnpjLookupSyncStatus::Success, $sync->status);
        $this->assertSame('org-001', $sync->organizationId);
        $this->assertSame(200, $sync->httpStatus);
        $this->assertSame($requestedAt, $sync->requestedAt);
        $this->assertSame($respondedAt, $sync->respondedAt);
        $this->assertSame(1000, $sync->durationMs);
        $this->assertSame(['cnpj' => '11222333000181'], $sync->requestPayload);
        $this->assertSame(['razao_social' => 'Agronorte Distribuidora'], $sync->responsePayload);
        $this->assertSame(['legal_name' => 'Agronorte Distribuidora'], $sync->normalizedPayload);
        $this->assertSame('abc123', $sync->responseHash);
    }

    public function test_it_represents_a_failed_cnpj_lookup_sync(): void
    {
        $sync = CnpjLookupSync::failed(
            cnpj: new Cnpj('11.222.333/0001-81'),
            provider: 'fake-provider',
            errorMessage: 'Provider unavailable.',
            errorCode: 'provider_unavailable',
            httpStatus: 503,
            requestPayload: ['cnpj' => '11222333000181'],
            responsePayload: ['message' => 'Service unavailable'],
        );

        $this->assertSame(CnpjLookupSyncStatus::Failed, $sync->status);
        $this->assertSame('provider_unavailable', $sync->errorCode);
        $this->assertSame('Provider unavailable.', $sync->errorMessage);
        $this->assertSame(503, $sync->httpStatus);
        $this->assertSame(['cnpj' => '11222333000181'], $sync->requestPayload);
        $this->assertSame(['message' => 'Service unavailable'], $sync->responsePayload);
        $this->assertSame([], $sync->normalizedPayload);
    }
}
