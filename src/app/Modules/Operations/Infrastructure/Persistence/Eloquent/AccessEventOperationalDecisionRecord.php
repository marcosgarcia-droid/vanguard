<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessEventOperationalDecision;
use App\Support\ActivityLog\LogsVanguardActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class AccessEventOperationalDecisionRecord extends Model
{
    use LogsVanguardActivity;

    protected $table =
        'access_event_operational_decisions';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'access_event_id',
        'tenant_id',
        'organization_id',
        'visitor_id',
        'visit_id',
        'version',
        'decision',
        'reason_code',
        'reason_message',
        'automatic_execution_enabled',
        'decided_at',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $decision): void {
            if (blank($decision->id)) {
                $decision->id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'decision' => AccessEventOperationalDecision::class,
            'automatic_execution_enabled' => 'boolean',
            'decided_at' => 'datetime',
        ];
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

    /**
     * A decisão será exibida no histórico do visitante quando
     * houver uma associação disponível.
     *
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
