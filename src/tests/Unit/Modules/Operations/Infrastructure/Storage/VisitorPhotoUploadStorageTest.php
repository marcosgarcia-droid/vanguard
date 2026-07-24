<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Storage;

use App\Modules\Operations\Infrastructure\Storage\VisitorPhotoUploadStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class VisitorPhotoUploadStorageTest extends TestCase
{
    public function test_it_stores_a_valid_private_visitor_photo(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()
            ->image(
                'visitante.jpg',
                720,
                900
            )
            ->size(500);

        $path = app(
            VisitorPhotoUploadStorage::class
        )->store($file);

        $this->assertStringStartsWith(
            'visitors/photos/',
            $path
        );

        Storage::disk('local')
            ->assertExists($path);
    }

    public function test_it_rejects_a_non_image_file(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()
            ->create(
                'documento.pdf',
                200,
                'application/pdf'
            );

        $this->expectException(
            ValidationException::class
        );

        app(
            VisitorPhotoUploadStorage::class
        )->store($file);
    }

    public function test_it_rejects_an_image_larger_than_five_megabytes(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()
            ->image('visitante.jpg')
            ->size(5200);

        $this->expectException(
            ValidationException::class
        );

        app(
            VisitorPhotoUploadStorage::class
        )->store($file);
    }
}
