<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visits\VisitAuthorizationMethod;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Support\ActivityLog\LogsVanguardActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class VisitRecord extends Model
{
    use LogsVanguardActivity, SoftDeletes;

    protected $table = 'visits';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'organization_id',
        'visitor_id',
        'host_employee_id',
        'partner_id',
        'status',
        'purpose',
        'expected_start_at',
        'expected_end_at',
        'arrived_by',
        'arrived_at',
        'authorizer_employee_id',
        'authorization_method',
        'authorization_notes',
        'authorized_by',
        'authorized_at',
        'identity_verified_by',
        'identity_verified_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'checked_in_by',
        'checked_in_at',
        'checked_out_by',
        'checked_out_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'notes',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $visit): void {
            if (blank($visit->id)) {
                $visit->id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'authorization_method' => VisitAuthorizationMethod::class,
            'expected_start_at' => 'datetime',
            'expected_end_at' => 'datetime',
            'arrived_at' => 'datetime',
            'authorized_at' => 'datetime',
            'identity_verified_at' => 'datetime',
            'rejected_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantRecord::class, 'tenant_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationRecord::class, 'organization_id');
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(VisitorRecord::class, 'visitor_id');
    }

    public function hostEmployee(): BelongsTo
    {
        return $this->belongsTo(EmployeeRecord::class, 'host_employee_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(PartnerRecord::class, 'partner_id');
    }

    public function vehicle(): HasOne
    {
        return $this->hasOne(
            VisitVehicleRecord::class,
            'visit_id'
        );
    }

    public function arrivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'arrived_by');
    }

    public function authorizerEmployee(): BelongsTo
    {
        return $this->belongsTo(
            EmployeeRecord::class,
            'authorizer_employee_id'
        );
    }

    /**
     * Usuário que registrou a autorização no sistema.
     */
    public function authorizationRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    /**
     * Mantido como alias para compatibilidade com a base inicial.
     */
    public function authorizedBy(): BelongsTo
    {
        return $this->authorizationRecordedBy();
    }

    public function identityVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'identity_verified_by'
        );
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
