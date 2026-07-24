<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Tests\TestCase;

final class FacialPhotoRecordTest extends TestCase
{
    public function test_it_uses_uuid_and_casts_facial_metadata(): void
    {
        $record = new FacialPhotoRecord([
            'source' => FacialPhotoSource::Webcam->value,
            'status' => FacialPhotoStatus::Approved->value,
            'width' => '800',
            'height' => '1000',
            'size_bytes' => '125000',
            'validation_result' => [
                'face_count' => 1,
            ],
            'rejection_reasons' => [],
        ]);

        $this->assertInstanceOf(HasMedia::class, $record);
        $this->assertSame('facial_photos', $record->getTable());
        $this->assertSame('string', $record->getKeyType());
        $this->assertFalse($record->getIncrementing());

        $this->assertSame(
            FacialPhotoSource::Webcam,
            $record->source
        );

        $this->assertSame(
            FacialPhotoStatus::Approved,
            $record->status
        );

        $this->assertSame(800, $record->width);
        $this->assertSame(1000, $record->height);
        $this->assertSame(125000, $record->size_bytes);

        $this->assertSame(
            ['face_count' => 1],
            $record->validation_result
        );
    }

    public function test_it_registers_one_private_original_image(): void
    {
        $record = new FacialPhotoRecord;

        $record->registerMediaCollections();

        $collection = collect($record->mediaCollections)
            ->first(
                fn ($collection): bool => $collection->name
                    === FacialPhotoRecord::ORIGINAL_COLLECTION
            );

        $this->assertNotNull($collection);
        $this->assertSame(
            'facial_photos',
            $collection->diskName
        );
        $this->assertTrue($collection->singleFile);

        $this->assertSame(
            [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            $collection->acceptsMimeTypes
        );
    }

    public function test_people_expose_facial_photo_relationships(): void
    {
        $this->assertInstanceOf(
            MorphMany::class,
            (new VisitorRecord)->facialPhotos()
        );

        $this->assertInstanceOf(
            MorphMany::class,
            (new EmployeeRecord)->facialPhotos()
        );
    }

    public function test_sensitive_analysis_fields_are_hidden(): void
    {
        $record = new FacialPhotoRecord;

        $this->assertContains('sha256', $record->getHidden());
        $this->assertContains(
            'validation_result',
            $record->getHidden()
        );
        $this->assertContains(
            'rejection_reasons',
            $record->getHidden()
        );
    }
}
