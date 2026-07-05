<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class OrganizationRecord extends Model
{
    use SoftDeletes;

    private const SOURCE_OPERATIONAL_MANUAL = 'operational_manual';

    protected $table = 'organizations';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'status',
        'cnpj',
        'cnpj_formatted',
        'cnpj_root',
        'cnpj_branch',
        'cnpj_check_digits',
        'legal_name',
        'trade_name',
        'display_name',
        'unit_code',
        'establishment_type',
        'is_head_office',
        'head_office_organization_id',
        'opened_at',
        'closed_at',
        'legal_nature_code',
        'legal_nature_name',
        'company_size_code',
        'company_size_name',
        'share_capital',
        'tax_registration_status_code',
        'tax_registration_status_name',
        'tax_registration_status_date',
        'tax_registration_status_reason',
        'special_status',
        'special_status_date',
        'responsible_federative_entity',
        'cnpj_synced_at',
        'cnpj_sync_provider',
        'cnpj_normalized_data',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_head_office' => 'boolean',
            'opened_at' => 'date',
            'closed_at' => 'date',
            'share_capital' => 'decimal:2',
            'tax_registration_status_date' => 'date',
            'special_status_date' => 'date',
            'cnpj_synced_at' => 'datetime',
            'cnpj_normalized_data' => 'array',
        ];
    }

    public function getOperationalNameAttribute(): string
    {
        return $this->display_name
            ?: $this->trade_name
            ?: $this->legal_name
            ?: $this->cnpj_formatted
            ?: $this->id;
    }

    public function getCityStateAttribute(): ?string
    {
        $address = $this->preferredAddress();

        if ($address === null) {
            return null;
        }

        $city = trim((string) $address->city);
        $state = trim((string) $address->state);

        if ($city === '' && $state === '') {
            return null;
        }

        if ($city === '') {
            return $state;
        }

        if ($state === '') {
            return $city;
        }

        return "{$city}/{$state}";
    }

    public function getPrimaryAddressLineAttribute(): ?string
    {
        $address = $this->preferredAddress();

        if ($address === null) {
            return null;
        }

        $parts = array_filter([
            trim((string) $address->street),
            trim((string) $address->number),
            trim((string) $address->district),
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    public function getPrimaryPostalCodeAttribute(): ?string
    {
        return $this->preferredAddress()?->postal_code;
    }

    public function getPrimaryPhoneDisplayAttribute(): ?string
    {
        return $this->preferredContact(['phone', 'telephone', 'mobile'])?->value;
    }

    public function getPrimaryEmailDisplayAttribute(): ?string
    {
        return $this->preferredContact(['email'])?->value;
    }

    public function getPrimaryContactDisplayAttribute(): ?string
    {
        return $this->primary_phone_display
            ?: $this->primary_email_display
            ?: $this->preferredContact()?->value;
    }

    private function preferredAddress(): ?OrganizationAddressRecord
    {
        $addresses = $this->relationLoaded('addresses')
            ? $this->addresses
            : $this->addresses()->get();

        return $addresses
            ->where('source', self::SOURCE_OPERATIONAL_MANUAL)
            ->firstWhere('is_primary', true)
            ?? $addresses->where('source', self::SOURCE_OPERATIONAL_MANUAL)->first()
            ?? $addresses->firstWhere('is_primary', true)
            ?? $addresses->first();
    }

    /**
     * @param  array<int, string>|null  $types
     */
    private function preferredContact(?array $types = null): ?OrganizationContactRecord
    {
        $contacts = $this->relationLoaded('contacts')
            ? $this->contacts
            : $this->contacts()->get();

        if ($types !== null) {
            $contacts = $contacts->filter(
                fn (OrganizationContactRecord $contact): bool => in_array((string) $contact->type, $types, true),
            );
        }

        return $contacts
            ->where('source', self::SOURCE_OPERATIONAL_MANUAL)
            ->firstWhere('is_primary', true)
            ?? $contacts->where('source', self::SOURCE_OPERATIONAL_MANUAL)->first()
            ?? $contacts->firstWhere('is_primary', true)
            ?? $contacts->first();
    }

    public function headOffice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'head_office_organization_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(self::class, 'head_office_organization_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrganizationAddressRecord::class, 'organization_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(OrganizationContactRecord::class, 'organization_id');
    }

    public function cnaeActivities(): HasMany
    {
        return $this->hasMany(OrganizationCnaeActivityRecord::class, 'organization_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMemberRecord::class, 'organization_id');
    }

    public function taxRegimes(): HasMany
    {
        return $this->hasMany(OrganizationTaxRegimeRecord::class, 'organization_id');
    }

    public function cnpjSyncs(): HasMany
    {
        return $this->hasMany(OrganizationCnpjSyncRecord::class, 'organization_id');
    }

    public function latestCnpjSync(): HasOne
    {
        return $this->hasOne(OrganizationCnpjSyncRecord::class, 'organization_id')
            ->latestOfMany();
    }
}
