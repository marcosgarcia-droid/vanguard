<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\Ingest;

use App\Modules\Operations\Application\AccessControl\Events\Ingest\AccessEventPayloadSanitizer;
use Tests\TestCase;

class AccessEventPayloadSanitizerTest extends TestCase
{
    public function test_it_keeps_only_explicit_non_biometric_fields(): void
    {
        $result = app(
            AccessEventPayloadSanitizer::class
        )->sanitize([
            'source' => 'simulator',
            'synthetic' => true,
            'sequence' => 7,
            'event_kind' => 'face_recognition',
            'result' => 'recognized',
            'face_image' => 'base64-data',
            'template' => 'biometric-template',
            'password' => 'secret',
            'nested' => [
                'image' => 'hidden',
            ],
        ]);

        $this->assertSame(
            [
                'source' => 'simulator',
                'synthetic' => true,
                'sequence' => 7,
                'event_kind' => 'face_recognition',
                'result' => 'recognized',
            ],
            $result
        );
    }

    public function test_it_removes_control_characters_and_non_scalar_values(): void
    {
        $result = app(
            AccessEventPayloadSanitizer::class
        )->sanitize([
            'source' => " simulator\0\n ",
            'synthetic' => true,
            'sequence' => [
                'invalid',
            ],
            'result' => '',
        ]);

        $this->assertSame(
            [
                'source' => 'simulator',
                'synthetic' => true,
            ],
            $result
        );
    }
}
