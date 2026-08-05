<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationAttemptStatus;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationAttemptRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

final class FacialCredentialSynchronizationRecordTest extends TestCase
{
    public function test_synchronization_model_exposes_safe_contracts(): void
    {
        $record = new FacialCredentialSynchronizationRecord;

        self::assertSame(
            'facial_credential_syncs',
            $record->getTable()
        );

        self::assertFalse($record->getIncrementing());
        self::assertSame('string', $record->getKeyType());

        $record->setRawAttributes(
            [
                'status' => 'pending',
                'version' => 2,
            ]
        );

        self::assertSame(
            FacialCredentialSynchronizationStatus::Pending,
            $record->status
        );

        self::assertSame(2, $record->version);

        self::assertInstanceOf(
            BelongsTo::class,
            $record->tenant()
        );

        self::assertInstanceOf(
            BelongsTo::class,
            $record->organization()
        );

        self::assertInstanceOf(
            BelongsTo::class,
            $record->visitor()
        );

        self::assertInstanceOf(
            BelongsTo::class,
            $record->facialPhoto()
        );

        self::assertInstanceOf(
            BelongsTo::class,
            $record->derivative()
        );

        self::assertInstanceOf(
            BelongsTo::class,
            $record->accessDevice()
        );

        self::assertInstanceOf(
            HasMany::class,
            $record->attempts()
        );

        self::assertInstanceOf(
            HasOne::class,
            $record->latestAttempt()
        );
    }

    public function test_attempt_model_exposes_safe_contracts(): void
    {
        $record = new FacialCredentialSynchronizationAttemptRecord;

        self::assertSame(
            'facial_credential_sync_attempts',
            $record->getTable()
        );

        self::assertFalse($record->getIncrementing());
        self::assertSame('string', $record->getKeyType());

        $record->setRawAttributes(
            [
                'status' => 'blocked',
                'attempt_number' => 3,
                'duration_ms' => 420,
            ]
        );

        self::assertSame(
            FacialCredentialSynchronizationAttemptStatus::Blocked,
            $record->status
        );

        self::assertSame(3, $record->attempt_number);
        self::assertSame(420, $record->duration_ms);

        self::assertInstanceOf(
            BelongsTo::class,
            $record->synchronization()
        );
    }

    public function test_synchronization_allows_only_status_updates(): void
    {
        $record = new FacialCredentialSynchronizationRecord;

        $record->setRawAttributes(
            [
                'status' => 'pending',
                'plan_fingerprint' => str_repeat('a', 64),
            ],
            true
        );

        $record->status =
            FacialCredentialSynchronizationStatus::Processing;

        $this->fireModelEvent(
            $record,
            'updating'
        );

        self::addToAssertionCount(1);
    }

    public function test_synchronization_rejects_context_updates(): void
    {
        $record = new FacialCredentialSynchronizationRecord;

        $record->setRawAttributes(
            [
                'status' => 'pending',
                'plan_fingerprint' => str_repeat('a', 64),
            ],
            true
        );

        $record->plan_fingerprint = str_repeat('b', 64);

        $this->expectException(RuntimeException::class);

        $this->fireModelEvent(
            $record,
            'updating'
        );
    }

    public function test_synchronization_cannot_be_deleted(): void
    {
        $record = new FacialCredentialSynchronizationRecord;

        $this->expectException(RuntimeException::class);

        $this->fireModelEvent(
            $record,
            'deleting'
        );
    }

    public function test_attempts_are_immutable(): void
    {
        $record = new FacialCredentialSynchronizationAttemptRecord;

        $record->setRawAttributes(
            [
                'status' => 'pending',
            ],
            true
        );

        $record->status =
            FacialCredentialSynchronizationAttemptStatus::Succeeded;

        $this->expectException(RuntimeException::class);

        $this->fireModelEvent(
            $record,
            'updating'
        );
    }

    public function test_attempts_cannot_be_deleted(): void
    {
        $record = new FacialCredentialSynchronizationAttemptRecord;

        $this->expectException(RuntimeException::class);

        $this->fireModelEvent(
            $record,
            'deleting'
        );
    }

    private function fireModelEvent(
        Model $model,
        string $event
    ): mixed {
        $method = new ReflectionMethod(
            Model::class,
            'fireModelEvent'
        );

        $method->setAccessible(true);

        return $method->invoke(
            $model,
            $event
        );
    }
}
