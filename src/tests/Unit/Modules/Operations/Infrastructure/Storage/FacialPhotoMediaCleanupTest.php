<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Storage;

use App\Modules\Operations\Infrastructure\Storage\FacialPhotoMediaCleanup;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class FacialPhotoMediaCleanupTest extends TestCase
{
    public function test_it_removes_only_an_individual_facial_media_directory(): void
    {
        Storage::fake('facial_photos');

        $directory =
            'tenants/tenant-1'
            .'/organizations/organization-1'
            .'/photos/photo-1'
            .'/media/media-1';

        $file = $directory
            .'/original/visitor.jpg';

        Storage::disk('facial_photos')
            ->put(
                $file,
                'synthetic-photo'
            );

        app(FacialPhotoMediaCleanup::class)
            ->remove([
                'disk' => 'facial_photos',
                'directory' => $directory,
            ]);

        Storage::disk('facial_photos')
            ->assertMissing($file);
    }

    public function test_it_refuses_to_remove_a_broad_or_unrelated_directory(): void
    {
        Storage::fake('facial_photos');

        $file =
            'tenants/tenant-1'
            .'/organizations/organization-1'
            .'/preserve.txt';

        Storage::disk('facial_photos')
            ->put(
                $file,
                'must-remain'
            );

        app(FacialPhotoMediaCleanup::class)
            ->remove([
                'disk' => 'facial_photos',
                'directory' => 'tenants/tenant-1'
                    .'/organizations/organization-1',
            ]);

        Storage::disk('facial_photos')
            ->assertExists($file);
    }

    public function test_it_accepts_null_as_a_no_operation_compensation(): void
    {
        Storage::fake('facial_photos');

        app(FacialPhotoMediaCleanup::class)
            ->remove(null);

        $this->assertSame(
            [],
            Storage::disk('facial_photos')
                ->allFiles()
        );
    }
}
