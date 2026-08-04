<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeAttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class FacialPhotoDerivativeAttemptRecord extends Model
{
    protected $table =
        'facial_photo_derivative_attempts';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'derivative_id',
        'facial_photo_id',
        'tenant_id',
        'organization_id',
        'requested_by',
        'requester_name',
        'attempt_number',
        'status',
        'normalizer',
        'normalizer_version',
        'source_sha256',
        'output_metadata',
        'failure_code',
        'started_at',
        'finished_at',
    ];

    protected $hidden = [
        'source_sha256',
        'output_metadata',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $attempt): void {
            if (blank($attempt->id)) {
                $attempt->id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'status' => FacialPhotoDerivativeAttemptStatus::class,
            'output_metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function derivative(): BelongsTo
    {
        return $this->belongsTo(
            FacialPhotoDerivativeRecord::class,
            'derivative_id'
        );
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by'
        );
    }
}
