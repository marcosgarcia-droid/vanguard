<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Simulator;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadException;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConnectionData;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Infrastructure\Integrations\Simulator\SimulatedAccessDeviceConfigurationReader;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SimulatedAccessDeviceConfigurationReaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'access_control.simulator_enabled',
            true
        );

        Http::fake();
    }

    public function test_it_returns_a_complete_synthetic_configuration_without_http(): void
    {
        $result = app(
            SimulatedAccessDeviceConfigurationReader::class
        )->read(
            $this->connection('success')
        );

        $this->assertSame(
            AccessDeviceConfigurationReadStatus::Success,
            $result->status
        );

        $this->assertSame(
            'SIMULATOR-1.0.0',
            $result->firmwareVersion
        );

        $this->assertSame(
            'Close',
            data_get(
                $result->configuration,
                'door.current_status'
            )
        );

        $this->assertSame(
            'success',
            data_get(
                $result->sanitizedResponse,
                'simulator.scenario'
            )
        );

        Http::assertSentCount(0);
    }

    public function test_it_returns_a_partial_synthetic_configuration(): void
    {
        $result = app(
            SimulatedAccessDeviceConfigurationReader::class
        )->read(
            $this->connection('partial')
        );

        $this->assertSame(
            AccessDeviceConfigurationReadStatus::Partial,
            $result->status
        );

        $this->assertNull(
            $result->firmwareVersion
        );

        $this->assertCount(
            1,
            $result->warnings
        );

        Http::assertSentCount(0);
    }

    public function test_it_can_simulate_a_failure_without_http(): void
    {
        try {
            app(
                SimulatedAccessDeviceConfigurationReader::class
            )->read(
                $this->connection('failed')
            );

            $this->fail(
                'Era esperada uma falha sintética.'
            );
        } catch (
            AccessDeviceConfigurationReadException $exception
        ) {
            $this->assertStringContainsString(
                'Falha sintética',
                $exception->getMessage()
            );
        }

        Http::assertSentCount(0);
    }

    private function connection(
        string $scenario
    ): AccessDeviceConnectionData {
        return new AccessDeviceConnectionData(
            deviceId: 'synthetic-device-001',
            protocol: 'http',
            ipAddress: '127.0.0.1',
            port: 1,
            username: 'simulator',
            password: 'synthetic-only',
            metadata: [
                'scenario' => $scenario,
            ],
        );
    }
}
