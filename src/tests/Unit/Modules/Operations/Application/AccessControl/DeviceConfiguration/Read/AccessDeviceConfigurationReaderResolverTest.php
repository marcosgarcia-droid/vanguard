<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReaderResolver;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\IntelbrasFacialReadOnlyReader;
use App\Modules\Operations\Infrastructure\Integrations\Simulator\SimulatedAccessDeviceConfigurationReader;
use InvalidArgumentException;
use Tests\TestCase;

class AccessDeviceConfigurationReaderResolverTest extends TestCase
{
    public function test_it_resolves_the_intelbras_reader(): void
    {
        $this->assertInstanceOf(
            IntelbrasFacialReadOnlyReader::class,
            app(
                AccessDeviceConfigurationReaderResolver::class
            )->resolve('intelbras')
        );
    }

    public function test_it_resolves_the_simulator_reader(): void
    {
        $this->assertInstanceOf(
            SimulatedAccessDeviceConfigurationReader::class,
            app(
                AccessDeviceConfigurationReaderResolver::class
            )->resolve('simulator')
        );
    }

    public function test_it_rejects_an_unknown_provider(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        app(
            AccessDeviceConfigurationReaderResolver::class
        )->resolve('unknown-provider');
    }
}
