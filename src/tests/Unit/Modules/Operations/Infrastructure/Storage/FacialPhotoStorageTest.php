<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Storage;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Storage\FacialPhotoPathGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class FacialPhotoStorageTest extends TestCase
{
    public function test_it_uses_a_private_dedicated_disk(): void
    {
        $this->assertSame(
            'facial_photos',
            config('media-library.disk_name')
        );

        $this->assertSame(
            'facial_photos',
            config('media-library.conversions_disk_name')
        );

        $this->assertSame(
            'local',
            config('filesystems.disks.facial_photos.driver')
        );

        $this->assertSame(
            storage_path('app/private/facial-photos'),
            config('filesystems.disks.facial_photos.root')
        );

        $this->assertSame(
            'private',
            config('filesystems.disks.facial_photos.visibility')
        );

        $this->assertFalse(
            config('filesystems.disks.facial_photos.serve')
        );

        $this->assertTrue(
            config('filesystems.disks.facial_photos.throw')
        );
    }

    public function test_it_generates_scoped_private_paths(): void
    {
        $photo = new FacialPhotoRecord([
            'id' => '11111111-1111-4111-8111-111111111111',
            'tenant_id' => '22222222-2222-4222-8222-222222222222',
            'organization_id' => '33333333-3333-4333-8333-333333333333',
        ]);

        $media = new Media;
        $media->setAttribute(
            'uuid',
            '44444444-4444-4444-8444-444444444444'
        );
        $media->setRelation('model', $photo);

        $generator = new FacialPhotoPathGenerator;

        $base =
            'tenants/22222222-2222-4222-8222-222222222222/'
            .'organizations/33333333-3333-4333-8333-333333333333/'
            .'photos/11111111-1111-4111-8111-111111111111/'
            .'media/44444444-4444-4444-8444-444444444444/';

        $this->assertSame(
            $base.'original/',
            $generator->getPath($media)
        );

        $this->assertSame(
            $base.'conversions/',
            $generator->getPathForConversions($media)
        );

        $this->assertSame(
            $base.'responsive-images/',
            $generator->getPathForResponsiveImages($media)
        );
    }

    public function test_media_migration_uses_uuid_model_ids(): void
    {
        $migrations = glob(
            database_path(
                'migrations/*_create_media_table.php'
            )
        );

        $this->assertCount(1, $migrations);

        $content = file_get_contents($migrations[0]);

        $this->assertIsString($content);

        $this->assertStringContainsString(
            "\$table->string('model_type');",
            $content
        );

        $this->assertStringContainsString(
            "\$table->uuid('model_id');",
            $content
        );

        $this->assertStringContainsString(
            'media_model_type_model_id_index',
            $content
        );

        $this->assertStringNotContainsString(
            "\$table->morphs('model');",
            $content
        );

        $this->assertStringNotContainsString(
            "\$table->uuidMorphs('model');",
            $content
        );
    }
}
