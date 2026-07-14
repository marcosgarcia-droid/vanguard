<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class AccessDeviceConfigurationSnapshotRecord extends Model
{
    protected $table =
        'access_device_configuration_snapshots';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'access_device_id',
        'tenant_id',
        'organization_id',
        'requested_by',
        'source',
        'status',
        'device_model',
        'firmware_version',
        'configuration',
        'capabilities',
        'sanitized_response',
        'configuration_hash',
        'read_at',
        'duration_ms',
        'message',
    ];

    protected static function booted(): void
    {
        self::creating(
            function (self $snapshot): void {
                if (blank($snapshot->id)) {
                    $snapshot->id =
                        (string) Str::uuid();
                }
            }
        );
    }

    protected function casts(): array
    {
        return [
            'source' => AccessDeviceConfigurationSource::class,
            'status' => AccessDeviceConfigurationReadStatus::class,
            'configuration' => 'array',
            'capabilities' => 'array',
            'sanitized_response' => 'array',
            'read_at' => 'datetime',
            'duration_ms' => 'integer',
        ];
    }

    public function accessDevice(): BelongsTo
    {
        return $this->belongsTo(
            AccessDeviceRecord::class,
            'access_device_id'
        );
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            TenantRecord::class,
            'tenant_id'
        );
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            OrganizationRecord::class,
            'organization_id'
        );
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by'
        );
    }
}
