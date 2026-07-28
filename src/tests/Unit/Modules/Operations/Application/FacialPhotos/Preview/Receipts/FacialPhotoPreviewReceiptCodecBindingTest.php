<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Preview\Receipts;

use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceiptCodec;
use App\Modules\Operations\Infrastructure\Images\Receipts\LaravelEncryptedFacialPhotoPreviewReceiptCodec;
use Tests\TestCase;

final class FacialPhotoPreviewReceiptCodecBindingTest extends TestCase
{
    public function test_it_resolves_the_laravel_encrypted_codec(): void
    {
        $codec = app(
            FacialPhotoPreviewReceiptCodec::class
        );

        $this->assertInstanceOf(
            LaravelEncryptedFacialPhotoPreviewReceiptCodec::class,
            $codec
        );
    }

    public function test_the_binding_is_transient(): void
    {
        $first = app(
            FacialPhotoPreviewReceiptCodec::class
        );

        $second = app(
            FacialPhotoPreviewReceiptCodec::class
        );

        $this->assertNotSame(
            $first,
            $second
        );
    }
}
