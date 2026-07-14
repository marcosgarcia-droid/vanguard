<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReader;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\IntelbrasFacialReadOnlyReader;
use Tests\TestCase;

class AccessDeviceConfigurationReaderBindingTest extends TestCase
{
    public function test_it_resolves_the_intelbras_read_only_reader(): void
    {
        $this->assertInstanceOf(
            IntelbrasFacialReadOnlyReader::class,
            app(AccessDeviceConfigurationReader::class)
        );
    }
}
