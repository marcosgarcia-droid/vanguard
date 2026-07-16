<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class AccessEventManualAssociationRecord extends Model
{
    protected $table =
        'access_event_manual_associations';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'access_event_id',
        'tenant_id',
        'organization_id',
        'idempotency_key',
        'previous_visitor_id',
        'previous_visit_id',
        'selected_visitor_id',
        'selected_visit_id',
        'operator_user_id',
        'operator_name',
        'previous_visitor_name',
        'previous_visit_reference',
        'selected_visitor_name',
        'selected_visit_reference',
        'reason',
        'resulting_status',
        'result_code',
        'result_message',
        'associated_at',
    ];

    protected static function booted(): void
    {
        self::creating(
            function (self $association): void {
                if (blank($association->id)) {
                    $association->id =
                        (string) Str::uuid();
                }
            }
        );

        self::updating(
            function (): never {
                throw new LogicException(
                    'Associações manuais de eventos são registros imutáveis.'
                );
            }
        );

        self::deleting(
            function (): never {
                throw new LogicException(
                    'Associações manuais de eventos não podem ser excluídas.'
                );
            }
        );
    }

    protected function casts(): array
    {
        return [
            'resulting_status' => AccessEventStatus::class,
            'associated_at' => 'datetime',
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

    public function previousVisitor(): BelongsTo
    {
        return $this->belongsTo(
            VisitorRecord::class,
            'previous_visitor_id'
        );
    }

    public function previousVisit(): BelongsTo
    {
        return $this->belongsTo(
            VisitRecord::class,
            'previous_visit_id'
        );
    }

    public function selectedVisitor(): BelongsTo
    {
        return $this->belongsTo(
            VisitorRecord::class,
            'selected_visitor_id'
        );
    }

    public function selectedVisit(): BelongsTo
    {
        return $this->belongsTo(
            VisitRecord::class,
            'selected_visit_id'
        );
    }

    public function operatorUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'operator_user_id'
        );
    }
}
