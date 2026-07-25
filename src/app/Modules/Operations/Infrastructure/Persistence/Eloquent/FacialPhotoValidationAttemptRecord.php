<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use RuntimeException;

final class FacialPhotoValidationAttemptRecord extends Model
{
    protected $table =
        'facial_photo_validation_attempts';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'facial_photo_id',
        'tenant_id',
        'organization_id',
        'operator_user_id',
        'operator_name',
        'attempt_number',
        'validator',
        'validator_version',
        'decision',
        'face_count',
        'metrics',
        'issues',
        'status_before',
        'status_after',
        'validated_at',
    ];

    /*
     * Diagnósticos técnicos permanecem acessíveis diretamente,
     * mas não aparecem em serializações genéricas do model.
     */
    protected $hidden = [
        'metrics',
        'issues',
    ];

    protected static function booted(): void
    {
        self::creating(
            function (self $attempt): void {
                if (blank($attempt->id)) {
                    $attempt->id =
                        (string) Str::uuid();
                }
            }
        );

        self::updating(
            function (): never {
                throw new RuntimeException(
                    'As tentativas de validação facial são registros imutáveis.'
                );
            }
        );

        self::deleting(
            function (): never {
                throw new RuntimeException(
                    'As tentativas de validação facial não podem ser excluídas.'
                );
            }
        );
    }

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'decision' => FacialPhotoValidationDecision::class,
            'face_count' => 'integer',
            'metrics' => 'array',
            'issues' => 'array',
            'status_before' => FacialPhotoStatus::class,
            'status_after' => FacialPhotoStatus::class,
            'validated_at' => 'datetime',
        ];
    }

    public function facialPhoto(): BelongsTo
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

    public function operatorUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'operator_user_id'
        );
    }
}
