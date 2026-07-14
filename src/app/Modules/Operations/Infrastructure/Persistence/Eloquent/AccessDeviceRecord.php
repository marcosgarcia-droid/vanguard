<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Support\ActivityLog\LogsVanguardActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class AccessDeviceRecord extends Model
{
    use LogsVanguardActivity, SoftDeletes;

    protected $table = 'access_devices';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'organization_id',
        'code',
        'name',
        'device_type',
        'provider',
        'model',
        'serial_number',
        'external_id',
        'ip_address',
        'port',
        'protocol',
        'auth_type',
        'credential_username',
        'credential_password',
        'direction',
        'status',
        'settings',
        'current_configuration',
        'desired_configuration',
        'capabilities',
        'configuration_read_at',
        'configuration_read_status',
        'configuration_read_message',
        'last_communication_at',
        'last_communication_status',
        'last_communication_message',
        'last_event_at',
        'notes',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $device): void {
            if (blank($device->id)) {
                $device->id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'direction' => AccessDeviceDirection::class,
            'status' => AccessDeviceStatus::class,
            'port' => 'integer',
            'credential_username' => 'encrypted',
            'credential_password' => 'encrypted',
            'settings' => 'array',
            'current_configuration' => 'array',
            'desired_configuration' => 'array',
            'capabilities' => 'array',
            'configuration_read_at' => 'datetime',
            'configuration_read_status' => AccessDeviceConfigurationReadStatus::class,
            'last_communication_at' => 'datetime',
            'last_event_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantRecord::class, 'tenant_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            OrganizationRecord::class,
            'organization_id'
        );
    }

    public function events(): HasMany
    {
        return $this->hasMany(
            AccessEventRecord::class,
            'access_device_id'
        );
    }

    public function configurationSnapshots(): HasMany
    {
        return $this->hasMany(
            AccessDeviceConfigurationSnapshotRecord::class,
            'access_device_id'
        );
    }

    public function getDisplayNameAttribute(): string
    {
        return collect([
            $this->code,
            $this->name,
        ])->filter()->implode(' - ');
    }

    public function hasConfiguredCredentials(): bool
    {
        return filled($this->credential_username)
            || filled($this->credential_password);
    }
}
