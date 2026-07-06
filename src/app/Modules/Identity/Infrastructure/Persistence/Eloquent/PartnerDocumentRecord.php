<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerDocumentRecord extends Model
{
    protected $table = 'partner_documents';

    protected $fillable = [
        'partner_id',
        'type',
        'number',
        'normalized_number',
        'state',
        'issuing_authority',
        'issued_at',
        'expires_at',
        'is_primary',
        'notes',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'is_primary' => 'boolean',
    ];

    public function setNumberAttribute(?string $value): void
    {
        $clean = self::cleanDocument($value);

        $this->attributes['number'] = $clean;
        $this->attributes['normalized_number'] = $clean;
    }

    public function setStateAttribute(?string $value): void
    {
        $this->attributes['state'] = $value ? strtoupper(trim($value)) : null;
    }

    public function setIsPrimaryAttribute(mixed $value): void
    {
        $this->attributes['is_primary'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(PartnerRecord::class, 'partner_id');
    }

    private static function cleanDocument(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', trim($value)));

        return $clean !== '' ? $clean : null;
    }
}
