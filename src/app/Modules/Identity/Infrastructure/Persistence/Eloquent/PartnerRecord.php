<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PartnerRecord extends Model
{
    use SoftDeletes;

    protected $table = 'partners';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'organization_id',
        'partner_code',
        'person_type',
        'name',
        'trade_name',
        'status',
        'profiles',
        'external_source',
        'external_id',
        'notes',
    ];

    protected $casts = [
        'profiles' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (! $record->id) {
                $record->id = (string) Str::uuid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantRecord::class, 'tenant_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationRecord::class, 'organization_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PartnerDocumentRecord::class, 'partner_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(PartnerAddressRecord::class, 'partner_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PartnerContactRecord::class, 'partner_id');
    }

    public function primaryDocument(string $type): ?PartnerDocumentRecord
    {
        if ($this->relationLoaded('documents')) {
            return $this->documents
                ->where('type', $type)
                ->sortByDesc('is_primary')
                ->first();
        }

        return $this->documents()
            ->where('type', $type)
            ->orderByDesc('is_primary')
            ->first();
    }

    public function primaryContact(string $type): ?PartnerContactRecord
    {
        if ($this->relationLoaded('contacts')) {
            return $this->contacts
                ->where('type', $type)
                ->sortByDesc('is_primary')
                ->first();
        }

        return $this->contacts()
            ->where('type', $type)
            ->orderByDesc('is_primary')
            ->first();
    }

    public function primaryAddress(): ?PartnerAddressRecord
    {
        if ($this->relationLoaded('addresses')) {
            return $this->addresses
                ->sortByDesc('is_primary')
                ->first();
        }

        return $this->addresses()
            ->orderByDesc('is_primary')
            ->first();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->trade_name ?: $this->name;
    }

    public function getCnpjAttribute(): ?string
    {
        return $this->primaryDocument('cnpj')?->number;
    }

    public function getCpfAttribute(): ?string
    {
        return $this->primaryDocument('cpf')?->number;
    }

    public function getPrimaryContactDisplayAttribute(): ?string
    {
        foreach (['mobile', 'whatsapp', 'phone', 'email'] as $type) {
            $contact = $this->primaryContact($type);

            if ($contact?->value) {
                return $contact->value;
            }
        }

        return null;
    }

    public function getCityStateAttribute(): ?string
    {
        $address = $this->primaryAddress();

        if (! $address) {
            return null;
        }

        return collect([$address->city, $address->state])
            ->filter()
            ->implode('/');
    }
}
