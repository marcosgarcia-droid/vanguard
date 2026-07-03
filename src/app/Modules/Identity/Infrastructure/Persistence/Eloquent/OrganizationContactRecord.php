<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class OrganizationContactRecord extends Model
{
    use SoftDeletes;

    protected $table = 'organization_contacts';

    protected $fillable = [
        'organization_id',
        'type',
        'label',
        'value',
        'normalized_value',
        'is_primary',
        'is_verified',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_verified' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationRecord::class, 'organization_id');
    }
}
