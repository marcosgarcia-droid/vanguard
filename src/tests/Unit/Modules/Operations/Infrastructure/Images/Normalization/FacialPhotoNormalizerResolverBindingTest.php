<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\Normalization;

use App\Modules\Operations\Application\FacialPhotos\Normalization\FacialPhotoNormalizerResolver;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeProfile;
use App\Modules\Operations\Infrastructure\Images\Normalization\ConfiguredFacialPhotoNormalizer;
use App\Modules\Operations\Infrastructure\Images\Normalization\MappedFacialPhotoNormalizerResolver;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialPhotoDescriptor;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class FacialPhotoNormalizerResolverBindingTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory =
            storage_path(
                'framework/testing/'
                .'facial-photo-intelbras-resolver'
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

    public function test_the_container_resolves_both_normalization_profiles(): void
    {
        config()->set(
            'facial_photos.normalization.enabled',
            true
        );

        config()->set(
            'facial_photos.normalization.temporary_directory',
            $this->directory.'/outputs'
        );

        $resolver = app(
            FacialPhotoNormalizerResolver::class
        );

        self::assertInstanceOf(
            MappedFacialPhotoNormalizerResolver::class,
            $resolver
        );

        self::assertInstanceOf(
            ConfiguredFacialPhotoNormalizer::class,
            $resolver->resolve(
                FacialPhotoDerivativeProfile::vanguardNormalized()
            )
        );

        self::assertInstanceOf(
            ConfiguredFacialPhotoNormalizer::class,
            $resolver->resolve(
                FacialPhotoDerivativeProfile::intelbrasFacialCredential()
            )
        );
    }

    public function test_the_real_intelbras_normalizer_produces_a_descriptor_compatible_derivative(): void
    {
        config()->set(
            'facial_photos.normalization.enabled',
            true
        );

        config()->set(
            'facial_photos.normalization.temporary_directory',
            $this->directory.'/outputs'
        );

        $source =
            $this->directory
            .'/source.jpg';

        $image =
            imagecreatetruecolor(
                800,
                1000
            );

        self::assertNotFalse(
            $image
        );

        $background =
            imagecolorallocate(
                $image,
                210,
                215,
                220
            );

        imagefilledrectangle(
            $image,
            0,
            0,
            799,
            999,
            $background
        );

        self::assertTrue(
            imagejpeg(
                $image,
                $source,
                92
            )
        );

        imagedestroy(
            $image
        );

        $result = app(
            FacialPhotoNormalizerResolver::class
        )
            ->resolve(
                FacialPhotoDerivativeProfile::intelbrasFacialCredential()
            )
            ->normalize(
                $source
            );

        new IntelbrasFacialPhotoDescriptor(
            sha256: $result->sha256,

            byteLength: $result->sizeBytes,

            width: $result->width,

            height: $result->height,

            mimeType: $result->mimeType,
        );

        self::assertSame(
            'intelbras_facial_credential',
            $result->profile->value
        );

        self::assertSame(
            'intelbras-facial-credential-v1',
            $result->policyVersion
        );

        self::assertSame(
            'image/jpeg',
            $result->mimeType
        );

        self::assertGreaterThanOrEqual(
            IntelbrasFacialPhotoDescriptor::MIN_WIDTH,
            $result->width
        );

        self::assertLessThanOrEqual(
            IntelbrasFacialPhotoDescriptor::MAX_WIDTH,
            $result->width
        );

        self::assertGreaterThanOrEqual(
            IntelbrasFacialPhotoDescriptor::MIN_HEIGHT,
            $result->height
        );

        self::assertLessThanOrEqual(
            IntelbrasFacialPhotoDescriptor::MAX_HEIGHT,
            $result->height
        );

        self::assertLessThanOrEqual(
            IntelbrasFacialPhotoDescriptor::MAX_BYTES,
            $result->sizeBytes
        );

        File::delete(
            $result->absolutePath
        );
    }
}
