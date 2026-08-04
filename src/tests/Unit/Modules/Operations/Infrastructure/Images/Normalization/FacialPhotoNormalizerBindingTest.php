<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\Normalization;

use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizationException;
use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizer;
use App\Modules\Operations\Infrastructure\Images\Normalization\ConfiguredFacialPhotoNormalizer;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class FacialPhotoNormalizerBindingTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path(
            'framework/testing/facial-photo-normalizer-binding'
        );

        File::deleteDirectory(
            $this->directory
        );

        File::ensureDirectoryExists(
            $this->directory
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(
            $this->directory
        );

        parent::tearDown();
    }

    public function test_it_resolves_fail_closed_by_default(): void
    {
        config()->set(
            'facial_photos.normalization.enabled',
            false
        );

        $normalizer = app(
            FacialPhotoNormalizer::class
        );

        $this->assertInstanceOf(
            ConfiguredFacialPhotoNormalizer::class,
            $normalizer
        );

        try {
            $normalizer->normalize(
                $this->directory.'/missing.jpg'
            );

            $this->fail(
                'A normalização deveria permanecer desativada.'
            );
        } catch (FacialPhotoNormalizationException $exception) {
            $this->assertSame(
                'normalization_disabled',
                $exception->failureCode
            );
        }
    }

    public function test_each_resolution_reads_the_current_configuration(): void
    {
        config()->set(
            'facial_photos.normalization.enabled',
            true
        );

        config()->set(
            'facial_photos.normalization.temporary_directory',
            $this->directory.'/outputs'
        );

        $source = $this->directory
            .'/source.jpg';

        $image = imagecreatetruecolor(
            240,
            320
        );

        $this->assertNotFalse(
            $image
        );

        $this->assertTrue(
            imagejpeg(
                $image,
                $source,
                90
            )
        );

        imagedestroy($image);

        $result = app(
            FacialPhotoNormalizer::class
        )->normalize($source);

        $this->assertSame(
            'image/jpeg',
            $result->mimeType
        );

        $this->assertFileExists(
            $result->absolutePath
        );
    }
}
