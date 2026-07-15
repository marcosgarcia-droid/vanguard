<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionSource;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalExecutionStatus;
use App\Support\ActivityLog\LogsVanguardActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class AccessEventOperationalExecutionRecord extends Model
{
    use LogsVanguardActivity;

    protected $table =
        'access_event_operational_executions';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'operational_decision_id',
        'access_event_id',
        'tenant_id',
        'organization_id',
        'visitor_id',
        'visit_id',
        'operator_user_id',
        'attempt_number',
        'source',
        'status',
        'reason_code',
        'reason_message',
        'automatic_execution_allowed',
        'visit_status_before',
        'visit_status_after',
        'attempted_at',
        'completed_at',
    ];

    protected static function booted(): void
    {
        self::creating(
            function (self $execution): void {
                if (blank($execution->id)) {
                    $execution->id =
                        (string) Str::uuid();
                }
            }
        );
    }

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'source' => AccessEventOperationalExecutionSource::class,
            'status' => AccessEventOperationalExecutionStatus::class,
            'automatic_execution_allowed' => 'boolean',
            'attempted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function operationalDecision(): BelongsTo
    {
        return $this->belongsTo(
            AccessEventOperationalDecisionRecord::class,
            'operational_decision_id'
        );
    }

    public function accessEvent(): BelongsTo
    {
        return $this->belongsTo(
            AccessEventRecord::class,
            'access_event_id'
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

    public function operatorUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'operator_user_id'
        );
    }

    /**
     * @return array{type: class-string, id: mixed}|null
     */
    protected function activityLogParentReference(): ?array
    {
        if (blank($this->visitor_id)) {
            return null;
        }

        return [
            'type' => VisitorRecord::class,
            'id' => $this->visitor_id,
        ];
    }
}
