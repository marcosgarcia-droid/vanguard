<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Support\ActivityLog\LogsVanguardActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeDocumentRecord extends Model
{
    use LogsVanguardActivity;

    protected $table = 'employee_documents';

    protected $fillable = [
        'employee_id',
        'type',
        'number',
        'issuing_authority',
        'issued_at',
        'expires_at',
        'is_primary',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
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
