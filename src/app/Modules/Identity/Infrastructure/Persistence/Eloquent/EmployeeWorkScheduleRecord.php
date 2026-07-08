<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EmployeeWorkScheduleRecord extends Model
{
    protected $table = 'employee_work_schedules';

    protected $fillable = [
        'employee_id',
        'employee_work_schedule_template_id',
        'name',
        'type',
        'weekly_workload_minutes',
        'daily_workload_minutes',
        'tolerance_before_start_minutes',
        'tolerance_after_end_minutes',
        'valid_from',
        'valid_until',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'weekly_workload_minutes' => 'integer',
            'daily_workload_minutes' => 'integer',
            'tolerance_before_start_minutes' => 'integer',
            'tolerance_after_end_minutes' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function setIsActiveAttribute(mixed $value): void
    {
        $this->attributes['is_active'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmployeeWorkScheduleTemplateRecord::class, 'employee_work_schedule_template_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeRecord::class, 'employee_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(EmployeeWorkScheduleDayRecord::class, 'employee_work_schedule_id');
    }
}
