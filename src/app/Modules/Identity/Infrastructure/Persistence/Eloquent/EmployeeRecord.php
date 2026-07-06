<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class EmployeeRecord extends Model
{
    use SoftDeletes;

    protected $table = 'employees';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'organization_id',
        'user_id',
        'manager_employee_id',
        'employee_code',
        'full_name',
        'preferred_name',
        'gender',
        'birth_date',
        'photo_disk',
        'photo_path',
        'photo_uploaded_at',
        'department',
        'position',
        'employment_type',
        'status',
        'hired_at',
        'terminated_at',
        'notes',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $employee): void {
            if (blank($employee->id)) {
                $employee->id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'photo_uploaded_at' => 'datetime',
            'hired_at' => 'date',
            'terminated_at' => 'date',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_employee_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_employee_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocumentRecord::class, 'employee_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(EmployeeAddressRecord::class, 'employee_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(EmployeeContactRecord::class, 'employee_id');
    }

    public function workSchedules(): HasMany
    {
        return $this->hasMany(EmployeeWorkScheduleRecord::class, 'employee_id');
    }

    public function currentWorkSchedule(): ?EmployeeWorkScheduleRecord
    {
        return $this->workSchedules()
            ->where('is_active', true)
            ->orderByDesc('valid_from')
            ->orderBy('id')
            ->first();
    }

    public function primaryDocument(string $type): ?EmployeeDocumentRecord
    {
        return $this->documents()
            ->where('type', $type)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    public function primaryContact(string $type): ?EmployeeContactRecord
    {
        return $this->contacts()
            ->where('type', $type)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    public function primaryAddress(): ?EmployeeAddressRecord
    {
        return $this->addresses()
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    public function getDisplayNameAttribute(): string
    {
        return filled($this->preferred_name)
            ? (string) $this->preferred_name
            : (string) $this->full_name;
    }

    public function getCpfAttribute(): ?string
    {
        return $this->primaryDocument('cpf')?->number;
    }

    public function getMobilePhoneAttribute(): ?string
    {
        return $this->primaryContact('mobile')?->value;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->primaryContact('phone')?->value;
    }
}
