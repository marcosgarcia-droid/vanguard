<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use RuntimeException;

final class FacialCredentialSynchronizationRecord extends Model
{
    use HasUuids;

    protected $table = 'facial_credential_syncs';

    protected $fillable = [
        'tenant_id',
        'organization_id',
        'visitor_id',
        'facial_photo_id',
        'facial_photo_derivative_id',
        'access_device_id',
        'operation',
        'status',
        'version',
        'plan_fingerprint',
        'context_fingerprint',
    ];

    protected $hidden = [
        'plan_fingerprint',
        'context_fingerprint',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        self::updating(
            function (self $record): void {
                $allowed = [
                    'status',
                    'updated_at',
                ];

                $unexpected = array_diff(
                    array_keys($record->getDirty()),
                    $allowed
                );

                if ($unexpected !== []) {
                    throw new RuntimeException(
                        'O contexto da sincronização facial é imutável.'
                    );
                }
            }
        );

        self::deleting(
            function (): void {
                throw new RuntimeException(
                    'Sincronizações faciais não podem ser excluídas.'
                );
            }
        );
    }

    protected function casts(): array
    {
        return [
            'status' => FacialCredentialSynchronizationStatus::class,
            'version' => 'integer',
        ];
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

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(
            VisitorRecord::class,
            'visitor_id'
        );
    }

    public function facialPhoto(): BelongsTo
    {
        return $this->belongsTo(
            FacialPhotoRecord::class,
            'facial_photo_id'
        );
    }

    public function derivative(): BelongsTo
    {
        return $this->belongsTo(
            FacialPhotoDerivativeRecord::class,
            'facial_photo_derivative_id'
        );
    }

    public function accessDevice(): BelongsTo
    {
        return $this->belongsTo(
            AccessDeviceRecord::class,
            'access_device_id'
        );
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(
            FacialCredentialSynchronizationAttemptRecord::class,
            'facial_credential_sync_id'
        );
    }

    public function latestAttempt(): HasOne
    {
        return $this
            ->hasOne(
                FacialCredentialSynchronizationAttemptRecord::class,
                'facial_credential_sync_id'
            )
            ->latestOfMany('attempt_number');
    }
}
