<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialPhotoDescriptor;
use InvalidArgumentException;
use Tests\TestCase;

final class IntelbrasFacialPhotoDescriptorTest extends TestCase
{
    public function test_it_represents_only_safe_photo_metadata(): void
    {
        $descriptor = new IntelbrasFacialPhotoDescriptor(
            sha256: str_repeat('a', 64),
            byteLength: 99_999,
            width: 500,
            height: 500,
        );

        $this->assertSame(
            str_repeat('a', 64),
            $descriptor->sha256
        );

        $this->assertSame(99_999, $descriptor->byteLength);
        $this->assertSame(500, $descriptor->width);
        $this->assertSame(500, $descriptor->height);
        $this->assertSame('image/jpeg', $descriptor->mimeType);

        $this->assertSame(
            [
                'sha256' => str_repeat('a', 64),
                'byte_length' => 99_999,
                'width' => 500,
                'height' => 500,
                'mime_type' => 'image/jpeg',
            ],
            $descriptor->fingerprintMaterial()
        );
    }

    public function test_it_rejects_a_photo_above_one_hundred_kilobytes(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFacialPhotoDescriptor(
            sha256: str_repeat('b', 64),
            byteLength: IntelbrasFacialPhotoDescriptor::MAX_BYTES + 1,
            width: 500,
            height: 500,
        );
    }

    public function test_it_rejects_invalid_dimensions(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFacialPhotoDescriptor(
            sha256: str_repeat('c', 64),
            byteLength: 50_000,
            width: 300,
            height: 601,
        );
    }

    public function test_it_rejects_an_unsupported_format(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFacialPhotoDescriptor(
            sha256: str_repeat('d', 64),
            byteLength: 50_000,
            width: 500,
            height: 500,
            mimeType: 'image/png',
        );
    }

    public function test_it_rejects_an_invalid_sha256(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFacialPhotoDescriptor(
            sha256: 'invalid',
            byteLength: 50_000,
            width: 500,
            height: 500,
        );
    }
}
