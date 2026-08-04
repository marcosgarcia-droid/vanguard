<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class FacialPhotoDerivativeRecord extends Model
{
    protected $table = 'facial_photo_derivatives';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'facial_photo_id',
        'tenant_id',
        'organization_id',
        'profile',
        'policy_version',
        'status',
        'source_sha256',
        'media_id',
        'width',
        'height',
        'mime_type',
        'size_bytes',
        'sha256',
        'generated_at',
        'failed_at',
        'last_failure_code',
    ];

    protected $hidden = [
        'source_sha256',
        'sha256',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $derivative): void {
            if (blank($derivative->id)) {
                $derivative->id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => FacialPhotoDerivativeStatus::class,
            'media_id' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'size_bytes' => 'integer',
            'generated_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(
            FacialPhotoRecord::class,
            'facial_photo_id'
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

    public function media(): BelongsTo
    {
        return $this->belongsTo(
            Media::class,
            'media_id'
        );
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(
            FacialPhotoDerivativeAttemptRecord::class,
            'derivative_id'
        );
    }

    public function latestAttempt(): HasOne
    {
        return $this
            ->hasOne(
                FacialPhotoDerivativeAttemptRecord::class,
                'derivative_id'
            )
            ->ofMany(
                'attempt_number',
                'max'
            );
    }

    public function isReady(): bool
    {
        return $this->status
            === FacialPhotoDerivativeStatus::Ready;
    }
}
