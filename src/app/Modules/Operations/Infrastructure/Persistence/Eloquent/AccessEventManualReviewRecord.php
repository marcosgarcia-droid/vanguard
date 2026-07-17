<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AccessEventManualReviewRecord extends Model
{
    use HasUuids;

    protected $table =
        'access_event_manual_reviews';

    protected $fillable = [
        'access_event_id',
        'operational_decision_id',
        'tenant_id',
        'organization_id',
        'visitor_id',
        'visit_id',
        'idempotency_key',
        'operator_user_id',
        'operator_name',
        'decision_version',
        'decision_reason_code',
        'decision_reason_message',
        'disposition',
        'notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'decision_version' => 'integer',
            'disposition' => AccessEventManualReviewDisposition::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(
            AccessEventRecord::class,
            'access_event_id'
        );
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(
            AccessEventOperationalDecisionRecord::class,
            'operational_decision_id'
        );
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'operator_user_id'
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

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException(
                'As análises manuais de eventos de acesso são imutáveis.'
            );
        });

        static::deleting(function (): never {
            throw new RuntimeException(
                'As análises manuais de eventos de acesso não podem ser excluídas.'
            );
        });
    }
}
