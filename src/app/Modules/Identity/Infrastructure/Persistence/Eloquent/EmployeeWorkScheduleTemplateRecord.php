<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class EmployeeWorkScheduleTemplateRecord extends Model
{
    use SoftDeletes;

    protected $table = 'employee_work_schedule_templates';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'code',
        'name',
        'type',
        'description',
        'weekly_workload_minutes',
        'daily_workload_minutes',
        'tolerance_before_start_minutes',
        'tolerance_after_end_minutes',
        'status',
        'is_system',
        'notes',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $template): void {
            if (blank($template->id)) {
                $template->id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'weekly_workload_minutes' => 'integer',
            'daily_workload_minutes' => 'integer',
            'tolerance_before_start_minutes' => 'integer',
            'tolerance_after_end_minutes' => 'integer',
            'is_system' => 'boolean',
        ];
    }

    public function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = self::normalizeCode($value);
    }

    public function setIsSystemAttribute(mixed $value): void
    {
        $this->attributes['is_system'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantRecord::class, 'tenant_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(EmployeeWorkScheduleTemplateDayRecord::class, 'employee_work_schedule_template_id');
    }

    public function employeeWorkSchedules(): HasMany
    {
        return $this->hasMany(EmployeeWorkScheduleRecord::class, 'employee_work_schedule_template_id');
    }

    public function getScheduleDisplayAttribute(): string
    {
        return filled($this->description)
            ? (string) $this->description
            : (string) $this->name;
    }

    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Ativa',
            'inactive' => 'Inativa',
            default => $this->status ?: '-',
        };
    }

    public function getTypeDisplayAttribute(): string
    {
        return match ($this->type) {
            'standard' => 'Padrão',
            'flexible' => 'Flexível',
            'shift_12x36' => 'Escala 12x36',
            'custom' => 'Personalizada',
            default => $this->type ?: '-',
        };
    }

    private static function normalizeCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $ascii = Str::ascii(trim($value));
        $code = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '_', $ascii));
        $code = trim($code, '_');

        return $code !== '' ? $code : null;
    }
}
