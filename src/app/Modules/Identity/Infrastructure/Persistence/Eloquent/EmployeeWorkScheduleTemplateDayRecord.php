<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeWorkScheduleTemplateDayRecord extends Model
{
    protected $table = 'employee_work_schedule_template_days';

    protected $fillable = [
        'employee_work_schedule_template_id',
        'weekday',
        'sequence',
        'is_working_day',
        'work_starts_at',
        'work_ends_at',
        'ends_next_day',
        'break_starts_at',
        'break_ends_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'sequence' => 'integer',
            'is_working_day' => 'boolean',
            'ends_next_day' => 'boolean',
        ];
    }

    public function setIsWorkingDayAttribute(mixed $value): void
    {
        $this->attributes['is_working_day'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function setEndsNextDayAttribute(mixed $value): void
    {
        $this->attributes['ends_next_day'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmployeeWorkScheduleTemplateRecord::class, 'employee_work_schedule_template_id');
    }
}
