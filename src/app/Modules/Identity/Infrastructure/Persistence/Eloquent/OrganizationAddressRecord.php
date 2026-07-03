<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class OrganizationAddressRecord extends Model
{
    use SoftDeletes;

    protected $table = 'organization_addresses';

    protected $fillable = [
        'organization_id',
        'type',
        'label',
        'postal_code',
        'street',
        'number',
        'complement',
        'district',
        'city',
        'city_code',
        'state',
        'country_code',
        'latitude',
        'longitude',
        'is_primary',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_primary' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationRecord::class, 'organization_id');
    }
}
