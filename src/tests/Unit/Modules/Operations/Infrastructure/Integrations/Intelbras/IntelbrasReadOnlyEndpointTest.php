<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\IntelbrasReadOnlyEndpoint;
use PHPUnit\Framework\TestCase;

class IntelbrasReadOnlyEndpointTest extends TestCase
{
    public function test_every_endpoint_is_explicitly_read_only(): void
    {
        foreach (
            IntelbrasReadOnlyEndpoint::cases() as $endpoint
        ) {
            $relativeUrl = strtolower(
                $endpoint->relativeUrl()
            );

            $this->assertStringContainsString(
                'action=get',
                $relativeUrl
            );

            $this->assertStringNotContainsString(
                'setconfig',
                $relativeUrl
            );

            $this->assertStringNotContainsString(
                'reboot',
                $relativeUrl
            );

            $this->assertStringNotContainsString(
                'opendoor',
                $relativeUrl
            );

            $this->assertStringNotContainsString(
                'capturecmd',
                $relativeUrl
            );

            $this->assertStringNotContainsString(
                'resetsystem',
                $relativeUrl
            );
        }
    }
}
