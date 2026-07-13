<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Support\ActivityLog\LogsVanguardActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ClassificationOptionRecord extends Model
{
    use LogsVanguardActivity, SoftDeletes;

    protected $table = 'classification_options';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'category',
        'code',
        'name',
        'description',
        'status',
        'sort_order',
        'is_system',
        'metadata',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_system' => 'boolean',
        'metadata' => 'array',
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

    public function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = self::normalizeCode($value);
    }

    public function setCategoryAttribute(?string $value): void
    {
        $this->attributes['category'] = self::normalizeCode($value);
    }

    public function getCategoryDisplayAttribute(): string
    {
        return match ($this->category) {
            'partner_profile' => 'Perfil de parceiro',
            'partner_document_type' => 'Tipo de documento de parceiro',
            'partner_contact_type' => 'Tipo de contato de parceiro',
            'partner_address_type' => 'Tipo de endereço de parceiro',
            default => $this->category ?: '-',
        };
    }

    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Ativa',
            'inactive' => 'Inativa',
            default => $this->status ?: '-',
        };
    }

    private static function normalizeCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $ascii = Str::ascii(trim($value));
        $code = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '_', $ascii));
        $code = trim($code, '_');

        return $code !== '' ? $code : null;
    }
}
