<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visits\VisitVehicleAuthorizationStatus;
use App\Support\ActivityLog\LogsVanguardActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

final class VisitVehicleAuthorizationRequestRecord extends Model
{
    use HasUuids;
    use LogsVanguardActivity;

    protected $table =
        'visit_vehicle_authorization_requests';

    protected $fillable = [
        'tenant_id',
        'organization_id',
        'visit_id',
        'visit_vehicle_id',
        'status',
        'pending_marker',
        'idempotency_key',
        'requested_by_user_id',
        'requested_by_name',
        'request_notes',
        'requested_at',
        'decided_by_user_id',
        'decided_by_name',
        'decision_notes',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VisitVehicleAuthorizationStatus::class,
            'pending_marker' => 'boolean',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (self $request): void {
            $originalStatus = VisitVehicleAuthorizationStatus::tryFrom(
                (string) $request->getRawOriginal('status')
            );

            if ($originalStatus?->isFinal()) {
                throw new RuntimeException(
                    'Solicitações de autorização de veículo já decididas são imutáveis.'
                );
            }
        });

        self::deleting(function (): never {
            throw new RuntimeException(
                'Solicitações de autorização de veículo não podem ser excluídas.'
            );
        });
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

    public function visit(): BelongsTo
    {
        return $this->belongsTo(
            VisitRecord::class,
            'visit_id'
        );
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(
            VisitVehicleRecord::class,
            'visit_vehicle_id'
        );
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by_user_id'
        );
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'decided_by_user_id'
        );
    }

    /**
     * @return array{type: class-string, id: mixed}|null
     */
    protected function activityLogParentReference(): ?array
    {
        if (blank($this->visit_id)) {
            return null;
        }

        return [
            'type' => VisitRecord::class,
            'id' => $this->visit_id,
        ];
    }
}
