<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialPhotoDescriptor;
use Tests\TestCase;

final class IntelbrasFacialDerivativeConfigurationTest extends TestCase
{
    public function test_derivative_limit_is_exactly_one_hundred_thousand_bytes(): void
    {
        $configuredLimit = config(
            'facial_photos.intelbras_derivative.maximum_size_bytes'
        );

        $this->assertSame(
            100_000,
            $configuredLimit
        );

        $this->assertSame(
            IntelbrasFacialPhotoDescriptor::MAX_BYTES,
            $configuredLimit
        );
    }

    public function test_configuration_does_not_use_binary_kilobytes_for_the_limit(): void
    {
        $contents = file_get_contents(
            config_path('facial_photos.php')
        );

        $this->assertIsString($contents);

        $this->assertStringNotContainsString(
            "'maximum_size_bytes' => 100 * 1024,",
            $contents
        );

        $this->assertStringContainsString(
            "'maximum_size_bytes' => 100_000,",
            $contents
        );
    }
}
