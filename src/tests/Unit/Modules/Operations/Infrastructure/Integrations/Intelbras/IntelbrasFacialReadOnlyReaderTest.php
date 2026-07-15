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

    public function test_it_discards_unknown_and_sensitive_fields_from_the_sanitized_response(): void
    {
        Http::fake(
            function (Request $request) {
                $url = $request->url();

                return match (true) {
                    str_contains(
                        $url,
                        'action=getCurrentTime'
                    ) => Http::response(
                        implode("\n", [
                            'result=2026-07-15 08:30:00',
                            'username=admin',
                            'password=secret-from-device',
                            'FaceData=synthetic-face-data',
                            'Image=synthetic-image-data',
                        ]),
                        200
                    ),

                    str_contains(
                        $url,
                        'action=getSoftwareVersion'
                    ) => Http::response(
                        implode("\n", [
                            'version=SYNTHETIC-FIRMWARE',
                            'FaceTemplate=synthetic-template-data',
                        ]),
                        200
                    ),

                    str_contains(
                        $url,
                        'name=AccessControlGeneral'
                    ) => Http::response(
                        implode("\n", [
                            'table.AccessControlGeneral.AccessProperty=bidirect',
                            'table.AccessControlGeneral.UserName=synthetic-user',
                            'table.AccessControlGeneral.CardNo=999999',
                        ]),
                        200
                    ),

                    str_contains(
                        $url,
                        'name=AccessControl'
                    ) => Http::response(
                        implode("\n", [
                            'table.AccessControl[0].Method=35',
                            'table.Users[0].Name=Synthetic Person',
                            'table.Users[0].CPF=00000000000',
                            'FaceTemplate=synthetic-biometric-data',
                        ]),
                        200
                    ),

                    str_contains(
                        $url,
                        'action=getDoorStatus'
                    ) => Http::response(
                        implode("\n", [
                            'Info.status=Close',
                            'Info.CardNo=123456',
                            'Info.EventUser=Synthetic Person',
                        ]),
                        200
                    ),

                    default => Http::response(
                        'Not Found',
                        404
                    ),
                };
            }
        );

        $result = app(
            AccessDeviceConfigurationReader::class
        )->read(
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
            [
                'result' => '2026-07-15 08:30:00',
            ],
            data_get(
                $result->sanitizedResponse,
                'current_time.values'
            )
        );

        $this->assertSame(
            [
                'version' => 'SYNTHETIC-FIRMWARE',
            ],
            data_get(
                $result->sanitizedResponse,
                'software_version.values'
            )
        );

        $this->assertSame(
            [
                'table.AccessControlGeneral.AccessProperty' => 'bidirect',
            ],
            data_get(
                $result->sanitizedResponse,
                'access_control_general.values'
            )
        );

        $this->assertSame(
            [
                'table.AccessControl[0].Method' => 35,
            ],
            data_get(
                $result->sanitizedResponse,
                'access_control.values'
            )
        );

        $this->assertSame(
            [
                'Info.status' => 'Close',
            ],
            data_get(
                $result->sanitizedResponse,
                'door_status.values'
            )
        );

        $serialized = json_encode(
            $result->sanitizedResponse,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ) ?: '';

        foreach (
            [
                'secret-from-device',
                'synthetic-face-data',
                'synthetic-image-data',
                'synthetic-template-data',
                'synthetic-user',
                '999999',
                'Synthetic Person',
                '00000000000',
                'synthetic-biometric-data',
                '123456',
            ] as $forbiddenValue
        ) {
            $this->assertStringNotContainsString(
                $forbiddenValue,
                $serialized
            );
        }
    }

    public function test_unknown_fields_in_an_optional_endpoint_produce_a_partial_read(): void
    {
        Http::fake(
            function (Request $request) {
                $url = $request->url();

                return match (true) {
                    str_contains(
                        $url,
                        'action=getCurrentTime'
                    ) => Http::response(
                        'result=2026-07-15 08:30:00',
                        200
                    ),

                    str_contains(
                        $url,
                        'action=getSoftwareVersion'
                    ) => Http::response(
                        implode("\n", [
                            'password=must-not-persist',
                            'FaceTemplate=must-not-persist',
                        ]),
                        200
                    ),

                    str_contains(
                        $url,
                        'name=AccessControlGeneral'
                    ) => Http::response(
                        'table.AccessControlGeneral.AccessProperty=bidirect',
                        200
                    ),

                    str_contains(
                        $url,
                        'name=AccessControl'
                    ) => Http::response(
                        'table.AccessControl[0].Method=35',
                        200
                    ),

                    str_contains(
                        $url,
                        'action=getDoorStatus'
                    ) => Http::response(
                        'Info.status=Close',
                        200
                    ),

                    default => Http::response(
                        'Not Found',
                        404
                    ),
                };
            }
        );

        $result = app(
            AccessDeviceConfigurationReader::class
        )->read(
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
            AccessDeviceConfigurationReadStatus::Partial,
            $result->status
        );

        $this->assertArrayNotHasKey(
            'software_version',
            $result->sanitizedResponse
        );

        $this->assertNull(
            $result->firmwareVersion
        );

        $this->assertCount(
            1,
            $result->warnings
        );

        $this->assertStringContainsString(
            'Versão do firmware',
            $result->warnings[0]
        );

        $serialized = json_encode(
            $result->sanitizedResponse
        ) ?: '';

        $this->assertStringNotContainsString(
            'must-not-persist',
            $serialized
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
