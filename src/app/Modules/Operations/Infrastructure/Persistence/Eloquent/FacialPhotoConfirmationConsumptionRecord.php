<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

final class FacialPhotoConfirmationConsumptionRecord extends Model
{
    use HasUuids;

    protected $table =
        'facial_photo_confirmation_consumptions';

    protected $fillable = [
        'facial_photo_id',
        'subject_type',
        'subject_id',
        'visitor_id',
        'tenant_id',
        'organization_id',
        'confirmed_by',
        'confirmation_key',
        'confirmation_context',
        'photo_sha256',
        'consumed_at',
    ];

    protected $hidden = [
        'confirmation_key',
        'confirmation_context',
        'photo_sha256',
    ];

    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
        ];
    }

    public function facialPhoto(): BelongsTo
    {
        return $this->belongsTo(
            FacialPhotoRecord::class,
            'facial_photo_id'
        );
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(
            VisitorRecord::class,
            'visitor_id'
        );
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            TenantRecord::class,
            'tenant_id'
        );
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            OrganizationRecord::class,
            'organization_id'
        );
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'confirmed_by'
        );
    }

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new RuntimeException(
                'Os consumos de confirmações faciais são imutáveis.'
            );
        });

        self::deleting(function (): never {
            throw new RuntimeException(
                'Os consumos de confirmações faciais não podem ser excluídos.'
            );
        });
    }
}
