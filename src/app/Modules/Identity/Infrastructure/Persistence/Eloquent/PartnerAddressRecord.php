<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Support\ActivityLog\LogsVanguardActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerAddressRecord extends Model
{
    use LogsVanguardActivity;

    protected $table = 'partner_addresses';

    protected $fillable = [
        'partner_id',
        'type',
        'postal_code',
        'street',
        'number',
        'complement',
        'district',
        'city',
        'state',
        'country_code',
        'is_primary',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function setPostalCodeAttribute(?string $value): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        $this->attributes['postal_code'] = $digits !== '' ? $digits : null;
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
}
