<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrganizationTaxRegimeRecord extends Model
{
    protected $table = 'organization_tax_regimes';

    protected $fillable = [
        'organization_id',
        'is_current',
        'is_simples_nacional',
        'simples_nacional_opted_at',
        'simples_nacional_excluded_at',
        'is_mei',
        'mei_opted_at',
        'mei_excluded_at',
        'tax_regime',
        'tax_regime_details',
        'effective_from',
        'effective_until',
        'synced_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'is_simples_nacional' => 'boolean',
            'simples_nacional_opted_at' => 'date',
            'simples_nacional_excluded_at' => 'date',
            'is_mei' => 'boolean',
            'mei_opted_at' => 'date',
            'mei_excluded_at' => 'date',
            'tax_regime_details' => 'array',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'synced_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationRecord::class, 'organization_id');
    }
}
