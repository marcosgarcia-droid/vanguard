<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeContactRecord extends Model
{
    protected $table = 'employee_contacts';

    protected $fillable = [
        'employee_id',
        'type',
        'label',
        'value',
        'is_primary',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function setIsPrimaryAttribute(mixed $value): void
    {
        $this->attributes['is_primary'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeRecord::class, 'employee_id');
    }
}
