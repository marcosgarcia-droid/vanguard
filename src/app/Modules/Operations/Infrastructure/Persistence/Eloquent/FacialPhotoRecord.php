<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

final class FacialPhotoRecord extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const ORIGINAL_COLLECTION = 'facial_original';

    protected $table = 'facial_photos';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'organization_id',
        'subject_type',
        'subject_id',
        'created_by',
        'source',
        'status',
        'captured_at',
        'analyzed_at',
        'approved_at',
        'rejected_at',
        'outdated_at',
        'width',
        'height',
        'mime_type',
        'size_bytes',
        'sha256',
        'validation_version',
        'validation_result',
        'rejection_reasons',
    ];

    protected $hidden = [
        'sha256',
        'validation_result',
        'rejection_reasons',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $photo): void {
            if (blank($photo->id)) {
                $photo->id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'source' => FacialPhotoSource::class,
            'status' => FacialPhotoStatus::class,
            'captured_at' => 'datetime',
            'analyzed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'outdated_at' => 'datetime',
            'width' => 'integer',
            'height' => 'integer',
            'size_bytes' => 'integer',
            'validation_result' => 'array',
            'rejection_reasons' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection(self::ORIGINAL_COLLECTION)
            ->useDisk('facial_photos')
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
            ])
            ->singleFile();
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

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function isApproved(): bool
    {
        return $this->status === FacialPhotoStatus::Approved;
    }
}
