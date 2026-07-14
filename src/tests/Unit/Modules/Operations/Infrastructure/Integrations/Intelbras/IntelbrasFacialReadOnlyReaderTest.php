<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReader;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadException;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConnectionData;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceCapabilityStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntelbrasFacialReadOnlyReaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'access_control.reads_enabled',
            true
        );

        config()->set(
            'access_control.allowed_cidrs',
            ['192.168.1.0/24']
        );
    }

    public function test_it_reads_and_normalizes_only_documented_get_endpoints(): void
    {
        Http::fake(
            function (Request $request) {
                $url = $request->url();

                return match (true) {
                    str_contains(
                        $url,
                        'action=getCurrentTime'
                    ) => Http::response(
                        'result=2026-07-14 15:20:30',
                        200,
                        ['Content-Type' => 'text/plain']
                    ),

                    str_contains(
                        $url,
                        'action=getSoftwareVersion'
                    ) => Http::response(
                        'version=2.000.00IB003.0.R,build:2021-06-22',
                        200,
                        ['Content-Type' => 'text/plain']
                    ),

                    str_contains(
                        $url,
                        'name=AccessControlGeneral'
                    ) => Http::response(
                        implode("\n", [
                            'table.AccessControlGeneral.AccessProperty=bidirect',
                            'table.AccessControlGeneral.ButtonExitEnable=true',
                            'table.AccessControlGeneral.SensorType=0',
                            'table.AccessControlGeneral.OpenDoorByCardEnable=true',
                        ]),
                        200,
                        ['Content-Type' => 'text/plain']
                    ),

                    str_contains(
                        $url,
                        'name=AccessControl'
                    ) => Http::response(
                        implode("\n", [
                            'table.AccessControl[0].BreakInAlarmEnable=true',
                            'table.AccessControl[0].DoorNotClosedAlarmEnable=true',
                            'table.AccessControl[0].DuressAlarmEnable=false',
                            'table.AccessControl[0].SensorEnable=true',
                            'table.AccessControl[0].CloseTimeout=10',
                            'table.AccessControl[0].Method=35',
                            'table.AccessControl[0].UnlockHoldInterval=3000',
                        ]),
                        200,
                        ['Content-Type' => 'text/plain']
                    ),

                    str_contains(
                        $url,
                        'action=getDoorStatus'
                    ) => Http::response(
                        'Info.status=Close',
                        200,
                        ['Content-Type' => 'text/plain']
                    ),

                    default => Http::response(
                        'Not Found',
                        404
                    ),
                };
            }
        );

        $reader = app(
            AccessDeviceConfigurationReader::class
        );

        $result = $reader->read(
            new AccessDeviceConnectionData(
                deviceId: 'device-demo',
                protocol: 'http',
                ipAddress: '192.168.1.201',
                port: 80,
                username: 'admin',
                password: 'synthetic-secret',
            )
        );

        $this->assertSame(
            AccessDeviceConfigurationReadStatus::Success,
            $result->status
        );

        $this->assertSame(
            '2026-07-14 15:20:30',
            data_get(
                $result->configuration,
                'device.date_time'
            )
        );

        $this->assertSame(
            'Close',
            data_get(
                $result->configuration,
                'door.current_status'
            )
        );

        $this->assertTrue(
            data_get(
                $result->configuration,
                'alarms.break_in_enabled'
            )
        );

        $this->assertSame(
            3.0,
            data_get(
                $result->configuration,
                'door.relay_activation_seconds'
            )
        );

        $this->assertSame(
            AccessDeviceCapabilityStatus::Supported->value,
            data_get(
                $result->capabilities,
                'alarms.door_open_enabled'
            )
        );

        $this->assertSame(
            '2.000.00IB003.0.R,build:2021-06-22',
            $result->firmwareVersion
        );

        $this->assertSame([], $result->warnings);

        Http::assertSentCount(5);

        Http::assertSent(
            fn (Request $request): bool => ! str_contains(
                strtolower($request->url()),
                'setconfig'
            )
                && ! str_contains(
                    strtolower($request->url()),
                    'reboot'
                )
                && ! str_contains(
                    strtolower($request->url()),
                    'opendoor'
                )
        );
    }

    public function test_authentication_failure_is_reported_without_exposing_credentials(): void
    {
        Http::fake([
            '*' => Http::response(
                'Unauthorized',
                401
            ),
        ]);

        $reader = app(
            AccessDeviceConfigurationReader::class
        );

        try {
            $reader->read(
                new AccessDeviceConnectionData(
                    deviceId: 'device-demo',
                    protocol: 'http',
                    ipAddress: '192.168.1.201',
                    port: 80,
                    username: 'admin',
                    password: 'never-expose-this-secret',
                )
            );

            $this->fail(
                'Era esperada uma falha de autenticação.'
            );
        } catch (
            AccessDeviceConfigurationReadException $exception
        ) {
            $this->assertStringContainsString(
                'credenciais foram recusadas',
                $exception->getMessage()
            );

            $this->assertStringNotContainsString(
                'never-expose-this-secret',
                $exception->getMessage()
            );
        }
    }
}
