<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationAttemptStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

final class FacialCredentialSynchronizationAttemptRecord extends Model
{
    use HasUuids;

    protected $table = 'facial_credential_sync_attempts';

    protected $fillable = [
        'facial_credential_sync_id',
        'attempt_number',
        'status',
        'provider',
        'scenario',
        'failure_code',
        'message',
        'duration_ms',
        'plan_fingerprint',
        'started_at',
        'completed_at',
    ];

    protected $hidden = [
        'plan_fingerprint',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        self::updating(
            function (): void {
                throw new RuntimeException(
                    'Tentativas de sincronização facial são imutáveis.'
                );
            }
        );

        self::deleting(
            function (): void {
                throw new RuntimeException(
                    'Tentativas de sincronização facial não podem ser excluídas.'
                );
            }
        );
    }

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'status' => FacialCredentialSynchronizationAttemptStatus::class,
            'duration_ms' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function synchronization(): BelongsTo
    {
        return $this->belongsTo(
            FacialCredentialSynchronizationRecord::class,
            'facial_credential_sync_id'
        );
    }
}
