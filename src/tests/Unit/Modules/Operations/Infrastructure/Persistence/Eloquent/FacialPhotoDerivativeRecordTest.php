<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeAttemptStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeAttemptRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

final class FacialPhotoDerivativeRecordTest extends TestCase
{
    public function test_derivative_model_exposes_safe_contracts(): void
    {
        $record = new FacialPhotoDerivativeRecord;

        $this->assertSame(
            'facial_photo_derivatives',
            $record->getTable()
        );

        $this->assertSame(
            'string',
            $record->getKeyType()
        );

        $this->assertFalse(
            $record->getIncrementing()
        );

        $this->assertContains(
            'source_sha256',
            $record->getHidden()
        );

        $this->assertContains(
            'sha256',
            $record->getHidden()
        );

        $record->setRawAttributes([
            'status' => 'ready',
        ]);

        $this->assertSame(
            FacialPhotoDerivativeStatus::Ready,
            $record->status
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            $record->photo()
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            $record->tenant()
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            $record->organization()
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            $record->media()
        );

        $this->assertInstanceOf(
            HasMany::class,
            $record->attempts()
        );

        $this->assertInstanceOf(
            HasOne::class,
            $record->latestAttempt()
        );
    }

    public function test_attempt_model_exposes_safe_contracts(): void
    {
        $record =
            new FacialPhotoDerivativeAttemptRecord;

        $this->assertSame(
            'facial_photo_derivative_attempts',
            $record->getTable()
        );

        $this->assertContains(
            'source_sha256',
            $record->getHidden()
        );

        $this->assertContains(
            'output_metadata',
            $record->getHidden()
        );

        $record->setRawAttributes([
            'status' => 'succeeded',
            'attempt_number' => 2,
            'output_metadata' => json_encode([
                'width' => 500,
                'height' => 700,
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertSame(
            FacialPhotoDerivativeAttemptStatus::Succeeded,
            $record->status
        );

        $this->assertSame(
            2,
            $record->attempt_number
        );

        $this->assertSame(
            [
                'width' => 500,
                'height' => 700,
            ],
            $record->output_metadata
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            $record->derivative()
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            $record->photo()
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            $record->requester()
        );
    }

    public function test_facial_photo_registers_private_derivatives(): void
    {
        $photo = new FacialPhotoRecord;

        $photo->registerMediaCollections();

        $collection = collect(
            $photo->mediaCollections
        )->first(
            static fn ($collection): bool => $collection->name
                    === FacialPhotoRecord::DERIVATIVE_COLLECTION
        );

        $this->assertNotNull($collection);

        $this->assertSame(
            'facial_photos',
            $collection->diskName
        );

        $this->assertFalse(
            $collection->singleFile
        );

        $this->assertSame(
            ['image/jpeg'],
            $collection->acceptsMimeTypes
        );

        $this->assertInstanceOf(
            HasMany::class,
            $photo->derivatives()
        );
    }

    public function test_normalization_is_configured_fail_closed(): void
    {
        $this->assertIsBool(
            config(
                'facial_photos.normalization.enabled'
            )
        );

        $this->assertSame(
            'vanguard_normalized',
            config(
                'facial_photos.normalization.default_profile'
            )
        );

        $this->assertSame(
            'vanguard-normalization-v1',
            config(
                'facial_photos.normalization.policy_version'
            )
        );
    }
}
