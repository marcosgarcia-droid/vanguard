<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSync;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSyncRepository;

final class EloquentCnpjLookupSyncRepository implements CnpjLookupSyncRepository
{
    public function save(CnpjLookupSync $sync): void
    {
        OrganizationCnpjSyncRecord::query()->create([
            'organization_id' => $sync->organizationId,
            'cnpj' => $sync->cnpj->value(),
            'provider' => $sync->provider,
            'endpoint' => $sync->endpoint,
            'status' => $sync->status->value,
            'http_status' => $sync->httpStatus,
            'requested_at' => $sync->requestedAt,
            'responded_at' => $sync->respondedAt,
            'duration_ms' => $sync->durationMs,
            'error_code' => $sync->errorCode,
            'error_message' => $sync->errorMessage,
            'request_payload' => $sync->requestPayload,
            'response_payload' => $sync->responsePayload,
            'normalized_payload' => $sync->normalizedPayload,
            'response_hash' => $sync->responseHash,
        ]);
    }
}
