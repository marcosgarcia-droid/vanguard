<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSync;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSyncRepository;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EloquentCnpjLookupSyncRepository;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationCnpjSyncRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentCnpjLookupSyncRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_implements_the_cnpj_lookup_sync_repository_contract(): void
    {
        $repository = new EloquentCnpjLookupSyncRepository;

        $this->assertInstanceOf(CnpjLookupSyncRepository::class, $repository);
    }

    public function test_it_saves_a_successful_cnpj_lookup_sync(): void
    {
        OrganizationRecord::query()->create([
            'id' => 'org-001',
            'legal_name' => 'Agronorte Distribuidora',
            'trade_name' => 'Agronorte',
            'status' => 'active',
        ]);

        $requestedAt = new DateTimeImmutable('2026-01-01 10:00:00');
        $respondedAt = new DateTimeImmutable('2026-01-01 10:00:01');

        $repository = new EloquentCnpjLookupSyncRepository;

        $repository->save(CnpjLookupSync::success(
            cnpj: new Cnpj('11.222.333/0001-81'),
            provider: 'fake-provider',
            responsePayload: [
                'razao_social' => 'Agronorte Distribuidora',
            ],
            normalizedPayload: [
                'legal_name' => 'Agronorte Distribuidora',
            ],
            organizationId: 'org-001',
            endpoint: 'https://example.test/cnpj/11222333000181',
            httpStatus: 200,
            requestedAt: $requestedAt,
            respondedAt: $respondedAt,
            durationMs: 1000,
            requestPayload: [
                'cnpj' => '11222333000181',
            ],
            responseHash: 'abc123',
        ));

        $record = OrganizationCnpjSyncRecord::query()->firstOrFail();

        $this->assertSame('org-001', $record->organization_id);
        $this->assertSame('11222333000181', $record->cnpj);
        $this->assertSame('fake-provider', $record->provider);
        $this->assertSame('https://example.test/cnpj/11222333000181', $record->endpoint);
        $this->assertSame('success', $record->status);
        $this->assertSame(200, $record->http_status);
        $this->assertSame(1000, $record->duration_ms);
        $this->assertSame(['cnpj' => '11222333000181'], $record->request_payload);
        $this->assertSame(['razao_social' => 'Agronorte Distribuidora'], $record->response_payload);
        $this->assertSame(['legal_name' => 'Agronorte Distribuidora'], $record->normalized_payload);
        $this->assertSame('abc123', $record->response_hash);
    }

    public function test_it_saves_a_failed_cnpj_lookup_sync(): void
    {
        $repository = new EloquentCnpjLookupSyncRepository;

        $repository->save(CnpjLookupSync::failed(
            cnpj: new Cnpj('11.222.333/0001-81'),
            provider: 'fake-provider',
            errorMessage: 'Provider unavailable.',
            errorCode: 'provider_unavailable',
            httpStatus: 503,
            requestPayload: [
                'cnpj' => '11222333000181',
            ],
            responsePayload: [
                'message' => 'Service unavailable',
            ],
        ));

        $record = OrganizationCnpjSyncRecord::query()->firstOrFail();

        $this->assertNull($record->organization_id);
        $this->assertSame('11222333000181', $record->cnpj);
        $this->assertSame('fake-provider', $record->provider);
        $this->assertSame('failed', $record->status);
        $this->assertSame(503, $record->http_status);
        $this->assertSame('provider_unavailable', $record->error_code);
        $this->assertSame('Provider unavailable.', $record->error_message);
        $this->assertSame(['cnpj' => '11222333000181'], $record->request_payload);
        $this->assertSame(['message' => 'Service unavailable'], $record->response_payload);
        $this->assertSame([], $record->normalized_payload);
    }
}
