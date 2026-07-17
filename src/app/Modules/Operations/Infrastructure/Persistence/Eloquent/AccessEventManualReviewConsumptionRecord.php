<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Operations\Domain\AccessControl\AccessEventManualReviewDisposition;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AccessEventManualReviewConsumptionRecord extends Model
{
    use HasUuids;

    protected $table =
        'access_event_manual_review_consumptions';

    protected $fillable = [
        'access_event_id',
        'manual_review_id',
        'operational_decision_id',
        'tenant_id',
        'organization_id',
        'visitor_id',
        'visit_id',
        'operator_user_id',
        'idempotency_key',
        'operator_name',
        'decision_version',
        'disposition',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'decision_version' => 'integer',

            'disposition' => AccessEventManualReviewDisposition::class,

            'consumed_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(
            AccessEventRecord::class,
            'access_event_id'
        );
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(
            AccessEventManualReviewRecord::class,
            'manual_review_id'
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
                'Os consumos de análises manuais são imutáveis.'
            );
        });

        static::deleting(function (): never {
            throw new RuntimeException(
                'Os consumos de análises manuais não podem ser excluídos.'
            );
        });
    }
}
