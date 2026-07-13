<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Support\ActivityLog\LogsVanguardActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerContactRecord extends Model
{
    use LogsVanguardActivity;

    protected $table = 'partner_contacts';

    protected $fillable = [
        'partner_id',
        'type',
        'label',
        'value',
        'normalized_value',
        'is_primary',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function setValueAttribute(?string $value): void
    {
        $value = $value !== null ? trim($value) : null;

        $this->attributes['value'] = $value;
        $this->attributes['normalized_value'] = $this->normalizeValue($value);
    }

    public function setIsPrimaryAttribute(mixed $value): void
    {
        $this->attributes['is_primary'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(PartnerRecord::class, 'partner_id');
    }

    private function normalizeValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (($this->attributes['type'] ?? null) === 'email') {
            return mb_strtolower($value);
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== '' ? $digits : mb_strtolower($value);
    }
}
