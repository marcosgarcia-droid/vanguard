<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Support\ActivityLog\LogsVanguardActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class VisitorRecord extends Model
{
    use LogsVanguardActivity, SoftDeletes;

    protected $table = 'visitors';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'organization_id',
        'partner_id',
        'visitor_code',
        'full_name',
        'preferred_name',
        'birth_date',
        'photo_disk',
        'photo_path',
        'photo_uploaded_at',
        'status',
        'external_source',
        'external_id',
        'notes',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $visitor): void {
            if (blank($visitor->id)) {
                $visitor->id = (string) Str::uuid();
            }
        });

        self::saving(function (self $visitor): void {
            $visitor->validateOperationalScope();

            if ($visitor->isDirty('photo_path')) {
                $visitor->photo_uploaded_at = filled($visitor->photo_path)
                    ? now()
                    : null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'photo_uploaded_at' => 'datetime',
            'status' => VisitorStatus::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantRecord::class, 'tenant_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationRecord::class, 'organization_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(PartnerRecord::class, 'partner_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VisitorDocumentRecord::class, 'visitor_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(VisitorContactRecord::class, 'visitor_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(VisitRecord::class, 'visitor_id');
    }

    public function primaryDocument(?string $type = null): ?VisitorDocumentRecord
    {
        if ($this->relationLoaded('documents')) {
            return $this->documents
                ->when(
                    filled($type),
                    fn ($documents) => $documents->where('type', $type)
                )
                ->sortByDesc('is_primary')
                ->first();
        }

        return $this->documents()
            ->when(
                filled($type),
                fn ($query) => $query->where('type', $type)
            )
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    public function primaryContact(?string $type = null): ?VisitorContactRecord
    {
        if ($this->relationLoaded('contacts')) {
            return $this->contacts
                ->when(
                    filled($type),
                    fn ($contacts) => $contacts->where('type', $type)
                )
                ->sortByDesc('is_primary')
                ->first();
        }

        return $this->contacts()
            ->when(
                filled($type),
                fn ($query) => $query->where('type', $type)
            )
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    public function getDisplayNameAttribute(): string
    {
        return filled($this->preferred_name)
            ? (string) $this->preferred_name
            : (string) $this->full_name;
    }

    public function getOfficialDocumentNumberAttribute(): ?string
    {
        return $this->primaryDocument()?->number;
    }

    public function getOfficialDocumentTypeAttribute(): ?string
    {
        return $this->primaryDocument()?->type;
    }

    public function getPrimaryContactDisplayAttribute(): ?string
    {
        foreach (['mobile', 'whatsapp', 'phone', 'email'] as $type) {
            $contact = $this->primaryContact($type);

            if (filled($contact?->value)) {
                return $contact->value;
            }
        }

        return null;
    }

    public static function normalizeDocumentNumber(
        ?string $type,
        ?string $value
    ): ?string {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (in_array($type, ['cpf', 'cnpj'], true)) {
            $digits = preg_replace('/\D+/', '', $value);

            return $digits !== '' ? $digits : null;
        }

        $normalized = strtoupper(
            preg_replace('/[^A-Za-z0-9]+/', '', $value)
        );

        return $normalized !== '' ? $normalized : null;
    }

    public static function documentExistsForOrganization(
        ?string $tenantId,
        ?string $organizationId,
        ?string $type,
        ?string $value,
        ?string $ignoreVisitorId = null,
    ): bool {
        $normalized = self::normalizeDocumentNumber($type, $value);

        if (
            blank($tenantId)
            || blank($organizationId)
            || blank($type)
            || blank($normalized)
        ) {
            return false;
        }

        return VisitorDocumentRecord::query()
            ->where('type', $type)
            ->where('normalized_number', $normalized)
            ->whereHas(
                'visitor',
                function ($query) use (
                    $tenantId,
                    $organizationId,
                    $ignoreVisitorId
                ): void {
                    $query
                        ->where('tenant_id', $tenantId)
                        ->where('organization_id', $organizationId);

                    if (filled($ignoreVisitorId)) {
                        $query->whereKeyNot($ignoreVisitorId);
                    }
                }
            )
            ->exists();
    }

    private function validateOperationalScope(): void
    {
        if (
            blank($this->tenant_id)
            || blank($this->organization_id)
        ) {
            return;
        }

        $organizationExists = OrganizationRecord::query()
            ->whereKey($this->organization_id)
            ->where('tenant_id', $this->tenant_id)
            ->exists();

        if (! $organizationExists) {
            throw ValidationException::withMessages([
                'organization_id' => 'A unidade selecionada não pertence ao grupo empresarial atual.',
            ]);
        }

        if (blank($this->partner_id)) {
            return;
        }

        $partnerExists = PartnerRecord::query()
            ->whereKey($this->partner_id)
            ->where('tenant_id', $this->tenant_id)
            ->where('organization_id', $this->organization_id)
            ->exists();

        if (! $partnerExists) {
            throw ValidationException::withMessages([
                'partner_id' => 'O parceiro selecionado não pertence à unidade informada.',
            ]);
        }
    }
}
