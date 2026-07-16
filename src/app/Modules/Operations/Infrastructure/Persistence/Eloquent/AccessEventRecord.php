<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

final class AccessEventRecord extends Model
{
    protected $table = 'access_events';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'access_device_id',
        'tenant_id',
        'organization_id',
        'visitor_id',
        'visit_id',
        'external_event_id',
        'external_person_id',
        'event_type',
        'direction',
        'occurred_at',
        'status',
        'result_code',
        'result_message',
        'raw_payload',
        'received_at',
        'processed_at',
        'processing_attempts',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $event): void {
            if (blank($event->id)) {
                $event->id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'direction' => AccessEventDirection::class,
            'status' => AccessEventStatus::class,
            'raw_payload' => 'array',
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'processing_attempts' => 'integer',
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
        return $this->belongsTo(TenantRecord::class, 'tenant_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            OrganizationRecord::class,
            'organization_id'
        );
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(
            VisitorRecord::class,
            'visitor_id'
        );
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(
            VisitRecord::class,
            'visit_id'
        );
    }

    public function operationalDecisions(): HasMany
    {
        return $this->hasMany(
            AccessEventOperationalDecisionRecord::class,
            'access_event_id'
        );
    }

    public function latestOperationalDecision(): HasOne
    {
        return $this->hasOne(
            AccessEventOperationalDecisionRecord::class,
            'access_event_id'
        )
            ->orderByDesc('version');
    }

    public function operationalExecutions(): HasMany
    {
        return $this->hasMany(
            AccessEventOperationalExecutionRecord::class,
            'access_event_id'
        );
    }

    public function latestOperationalExecution(): HasOne
    {
        return $this->hasOne(
            AccessEventOperationalExecutionRecord::class,
            'access_event_id'
        )
            ->orderByDesc('attempted_at')
            ->orderByDesc('created_at');
    }
}
