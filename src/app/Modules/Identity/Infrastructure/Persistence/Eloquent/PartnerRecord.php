<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Support\ActivityLog\LogsVanguardActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PartnerRecord extends Model
{
    use LogsVanguardActivity, SoftDeletes;

    public const OFFICIAL_DOCUMENT_TYPES = ['cpf', 'cnpj'];

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

    public function otherDocuments(): HasMany
    {
        return $this->hasMany(PartnerDocumentRecord::class, 'partner_id')
            ->whereNotIn('type', self::OFFICIAL_DOCUMENT_TYPES);
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

    public function officialDocument(): ?PartnerDocumentRecord
    {
        if ($this->relationLoaded('documents')) {
            return $this->documents
                ->whereIn('type', self::OFFICIAL_DOCUMENT_TYPES)
                ->sortByDesc('is_primary')
                ->first();
        }

        return $this->documents()
            ->whereIn('type', self::OFFICIAL_DOCUMENT_TYPES)
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

    public function getOfficialDocumentNumberAttribute(): ?string
    {
        return $this->officialDocument()?->number;
    }

    public function getOfficialDocumentTypeAttribute(): ?string
    {
        return $this->officialDocument()?->type;
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

    public function syncOfficialDocument(?string $value): void
    {
        $number = self::normalizeOfficialDocument($value);
        $type = self::officialDocumentTypeFromNumber($number);

        if (! $number || ! $type) {
            return;
        }

        $this->documents()
            ->whereIn('type', self::OFFICIAL_DOCUMENT_TYPES)
            ->delete();

        $this->documents()->create([
            'type' => $type,
            'number' => $number,
            'is_primary' => true,
        ]);
    }

    public static function normalizeOfficialDocument(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    public static function officialDocumentTypeFromNumber(?string $value): ?string
    {
        $number = self::normalizeOfficialDocument($value);

        return match (strlen((string) $number)) {
            11 => 'cpf',
            14 => 'cnpj',
            default => null,
        };
    }

    public static function personTypeFromOfficialDocument(?string $value): ?string
    {
        return match (self::officialDocumentTypeFromNumber($value)) {
            'cpf' => 'individual',
            'cnpj' => 'company',
            default => null,
        };
    }

    public static function officialDocumentExistsForTenant(?string $tenantId, ?string $value, ?string $ignorePartnerId = null): bool
    {
        $number = self::normalizeOfficialDocument($value);

        if (! $tenantId || ! $number) {
            return false;
        }

        return PartnerDocumentRecord::query()
            ->where('normalized_number', $number)
            ->whereHas('partner', function ($query) use ($tenantId, $ignorePartnerId): void {
                $query->where('tenant_id', $tenantId);

                if ($ignorePartnerId) {
                    $query->whereKeyNot($ignorePartnerId);
                }
            })
            ->exists();
    }
}
