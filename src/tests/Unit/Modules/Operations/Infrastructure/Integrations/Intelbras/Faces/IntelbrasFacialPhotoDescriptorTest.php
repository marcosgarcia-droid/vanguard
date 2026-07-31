<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialPhotoDescriptor;
use InvalidArgumentException;
use Tests\TestCase;

final class IntelbrasFacialPhotoDescriptorTest extends TestCase
{
    public function test_it_represents_a_safe_synthetic_jpeg(): void
    {
        $bytes = $this->syntheticJpegBytes(1_024);

        $descriptor = new IntelbrasFacialPhotoDescriptor(
            base64: base64_encode($bytes),
            byteLength: strlen($bytes),
            width: 500,
            height: 500,
        );

        $this->assertSame(
            1_024,
            $descriptor->byteLength
        );

        $this->assertSame(
            hash('sha256', $bytes),
            $descriptor->sha256
        );

        $this->assertSame(
            [
                'byte_length' => 1_024,
                'width' => 500,
                'height' => 500,
                'sha256' => hash('sha256', $bytes),
            ],
            $descriptor->toSafeArray()
        );

        $this->assertArrayNotHasKey(
            'base64',
            $descriptor->toSafeArray()
        );
    }

    public function test_it_rejects_a_photo_above_one_hundred_kilobytes(): void
    {
        $bytes = $this->syntheticJpegBytes(
            IntelbrasFacialPhotoDescriptor::MAX_BYTES + 1
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFacialPhotoDescriptor(
            base64: base64_encode($bytes),
            byteLength: strlen($bytes),
            width: 500,
            height: 500,
        );
    }

    public function test_it_rejects_invalid_dimensions(): void
    {
        $bytes = $this->syntheticJpegBytes(1_024);

        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFacialPhotoDescriptor(
            base64: base64_encode($bytes),
            byteLength: strlen($bytes),
            width: 149,
            height: 300,
        );
    }

    public function test_it_rejects_non_jpeg_content(): void
    {
        $bytes = str_repeat('A', 1_024);

        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFacialPhotoDescriptor(
            base64: base64_encode($bytes),
            byteLength: strlen($bytes),
            width: 500,
            height: 500,
        );
    }

    private function syntheticJpegBytes(
        int $byteLength
    ): string {
        return "\xFF\xD8"
            .str_repeat('A', $byteLength - 4)
            ."\xFF\xD9";
    }
}
