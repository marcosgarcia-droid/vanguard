<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrganizationCnpjSyncRecord extends Model
{
    protected $table = 'organization_cnpj_syncs';

    protected $fillable = [
        'organization_id',
        'cnpj',
        'provider',
        'endpoint',
        'status',
        'http_status',
        'requested_at',
        'responded_at',
        'duration_ms',
        'error_code',
        'error_message',
        'request_payload',
        'response_payload',
        'normalized_payload',
        'response_hash',
    ];

    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
            'duration_ms' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'normalized_payload' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationRecord::class, 'organization_id');
    }
}
