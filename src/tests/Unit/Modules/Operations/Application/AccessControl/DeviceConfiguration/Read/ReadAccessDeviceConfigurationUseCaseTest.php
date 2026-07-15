<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReader;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadException;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadGuard;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadGuardException;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadLease;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadResult;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConnectionData;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\ReadAccessDeviceConfigurationCommand;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\ReadAccessDeviceConfigurationException;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\ReadAccessDeviceConfigurationUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceCapabilityStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceConfigurationSnapshotRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ReadAccessDeviceConfigurationUseCaseTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_disabled_reads_do_not_call_the_device_reader(): void
    {
        [
            $device,
            $user,
        ] = $this->createDeviceContext();

        config()->set(
            'access_control.reads_enabled',
            false
        );

        $reader = new class implements AccessDeviceConfigurationReader
        {
            public bool $called = false;

            public function read(
                AccessDeviceConnectionData $connection
            ): AccessDeviceConfigurationReadResult {
                $this->called = true;

                throw new \RuntimeException(
                    'O reader não deveria ser chamado.'
                );
            }
        };

        $this->app->instance(
            AccessDeviceConfigurationReader::class,
            $reader
        );

        try {
            app(
                ReadAccessDeviceConfigurationUseCase::class
            )->execute(
                new ReadAccessDeviceConfigurationCommand(
                    deviceId: $device->id,
                    requestedByUserId: $user->id,
                )
            );

            $this->fail(
                'Era esperado o bloqueio da leitura.'
            );
        } catch (
            ReadAccessDeviceConfigurationException $exception
        ) {
            $this->assertNull(
                $exception->snapshotId
            );

            $this->assertStringContainsString(
                'desativada neste ambiente',
                $exception->getMessage()
            );
        }

        $this->assertFalse($reader->called);

        $this->assertDatabaseCount(
            'access_device_configuration_snapshots',
            0
        );
    }

    public function test_guard_rejection_does_not_call_the_reader_or_create_a_snapshot(): void
    {
        [
            $device,
            $user,
        ] = $this->createDeviceContext();

        $reader = new class implements AccessDeviceConfigurationReader
        {
            public bool $called = false;

            public function read(
                AccessDeviceConnectionData $connection
            ): AccessDeviceConfigurationReadResult {
                $this->called = true;

                throw new \RuntimeException(
                    'O reader não deveria ser chamado.'
                );
            }
        };

        $guard = new class implements AccessDeviceConfigurationReadGuard
        {
            public function acquire(
                string $deviceId
            ): AccessDeviceConfigurationReadLease {
                throw new AccessDeviceConfigurationReadGuardException(
                    'Já existe uma leitura em andamento para este dispositivo.'
                );
            }
        };

        $this->app->instance(
            AccessDeviceConfigurationReader::class,
            $reader
        );

        $this->app->instance(
            AccessDeviceConfigurationReadGuard::class,
            $guard
        );

        try {
            app(
                ReadAccessDeviceConfigurationUseCase::class
            )->execute(
                new ReadAccessDeviceConfigurationCommand(
                    deviceId: $device->id,
                    requestedByUserId: $user->id,
                )
            );

            $this->fail(
                'Era esperado o bloqueio pelo guard.'
            );
        } catch (
            ReadAccessDeviceConfigurationException $exception
        ) {
            $this->assertNull(
                $exception->snapshotId
            );

            $this->assertStringContainsString(
                'leitura em andamento',
                $exception->getMessage()
            );
        }

        $this->assertFalse(
            $reader->called
        );

        $this->assertDatabaseCount(
            'access_device_configuration_snapshots',
            0
        );
    }

    public function test_it_reads_persists_and_audits_device_configuration(): void
    {
        [
            $device,
            $user,
        ] = $this->createDeviceContext();

        $this->app->instance(
            AccessDeviceConfigurationReader::class,
            new class implements AccessDeviceConfigurationReader
            {
                public function read(
                    AccessDeviceConnectionData $connection
                ): AccessDeviceConfigurationReadResult {
                    return new AccessDeviceConfigurationReadResult(
                        status: AccessDeviceConfigurationReadStatus::Success,
                        configuration: [
                            'device' => [
                                'date_time' => '2026-07-14 16:00:00',
                                'firmware_version' => 'DEMO-FIRMWARE',
                            ],
                            'door' => [
                                'current_status' => 'Close',
                                'relay_activation_seconds' => 3.0,
                            ],
                            'alarms' => [
                                'door_open_enabled' => true,
                            ],
                        ],
                        capabilities: [
                            'alarms' => [
                                'door_open_enabled' => AccessDeviceCapabilityStatus::Supported
                                    ->value,
                            ],
                        ],
                        sanitizedResponse: [
                            'current_time' => [
                                'endpoint' => '/cgi-bin/global.cgi?action=getCurrentTime',
                                'values' => [
                                    'result' => '2026-07-14 16:00:00',
                                ],
                            ],
                        ],
                        firmwareVersion: 'DEMO-FIRMWARE',
                        durationMs: 125,
                        message: 'Leitura somente leitura concluída com sucesso.',
                    );
                }
            }
        );

        $result = app(
            ReadAccessDeviceConfigurationUseCase::class
        )->execute(
            new ReadAccessDeviceConfigurationCommand(
                deviceId: $device->id,
                requestedByUserId: $user->id,
            )
        );

        $device->refresh();

        $snapshot =
            AccessDeviceConfigurationSnapshotRecord::query()
                ->findOrFail(
                    $result->snapshotId
                );

        $this->assertSame(
            AccessDeviceConfigurationReadStatus::Success,
            $device->configuration_read_status
        );

        $this->assertSame(
            'Close',
            data_get(
                $device->current_configuration,
                'door.current_status'
            )
        );

        $this->assertSame(
            3,
            data_get(
                $device->current_configuration,
                'door.relay_activation_seconds'
            )
        );

        $this->assertSame(
            'DEMO-FIRMWARE',
            $snapshot->firmware_version
        );

        $this->assertSame(
            $user->id,
            $snapshot->requested_by
        );

        $this->assertNotNull(
            $snapshot->configuration_hash
        );

        $activity = Activity::query()
            ->where(
                'subject_type',
                AccessDeviceRecord::class
            )
            ->where(
                'subject_id',
                $device->id
            )
            ->where(
                'event',
                'configuration_read'
            )
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $snapshot->id,
            data_get(
                $activity->properties,
                'snapshot_id'
            )
        );

        $this->assertSame(
            (string) $user->id,
            (string) $activity->causer_id
        );

        $serialized =
            json_encode(
                $activity->properties
            ) ?: '';

        $this->assertStringNotContainsString(
            'synthetic-secret',
            $serialized
        );
    }

    public function test_partial_read_merges_observed_values_and_preserves_the_last_known_state(): void
    {
        [
            $device,
            $user,
        ] = $this->createDeviceContext();

        $device->forceFill([
            'current_configuration' => [
                'device' => [
                    'date_time' => '2026-07-14 16:00:00',
                    'firmware_version' => 'FIRMWARE-ANTERIOR',
                ],
                'door' => [
                    'current_status' => 'Open',
                    'relay_activation_seconds' => 3,
                ],
                'alarms' => [
                    'door_open_enabled' => true,
                ],
            ],
            'capabilities' => [
                'device' => [
                    'firmware_version' => AccessDeviceCapabilityStatus::Supported->value,
                ],
                'door' => [
                    'current_status' => AccessDeviceCapabilityStatus::Supported->value,
                    'relay_activation_seconds' => AccessDeviceCapabilityStatus::Supported->value,
                ],
                'alarms' => [
                    'door_open_enabled' => AccessDeviceCapabilityStatus::Supported->value,
                ],
            ],
        ])->saveQuietly();

        $this->app->instance(
            AccessDeviceConfigurationReader::class,
            new class implements AccessDeviceConfigurationReader
            {
                public function read(
                    AccessDeviceConnectionData $connection
                ): AccessDeviceConfigurationReadResult {
                    return new AccessDeviceConfigurationReadResult(
                        status: AccessDeviceConfigurationReadStatus::Partial,
                        configuration: [
                            'device' => [
                                'date_time' => '2026-07-15 08:30:00',
                            ],
                            'door' => [
                                'current_status' => 'Close',
                            ],
                        ],
                        capabilities: [
                            'device' => [
                                'date_time' => AccessDeviceCapabilityStatus::Supported
                                    ->value,
                            ],
                            'door' => [
                                'current_status' => AccessDeviceCapabilityStatus::Supported
                                    ->value,
                            ],
                        ],
                        sanitizedResponse: [
                            'current_time' => [
                                'endpoint' => '/cgi-bin/global.cgi?action=getCurrentTime',
                                'values' => [
                                    'result' => '2026-07-15 08:30:00',
                                ],
                            ],
                            'door_status' => [
                                'endpoint' => '/cgi-bin/accessControl.cgi?action=getDoorStatus&channel=1',
                                'values' => [
                                    'Info.status' => 'Close',
                                ],
                            ],
                        ],
                        firmwareVersion: null,
                        durationMs: 80,
                        message: 'Leitura concluída parcialmente.',
                        warnings: [
                            'Versão do firmware: resposta não disponível.',
                        ],
                    );
                }
            }
        );

        $result = app(
            ReadAccessDeviceConfigurationUseCase::class
        )->execute(
            new ReadAccessDeviceConfigurationCommand(
                deviceId: $device->id,
                requestedByUserId: $user->id,
            )
        );

        $device->refresh();

        $snapshot =
            AccessDeviceConfigurationSnapshotRecord::query()
                ->findOrFail($result->snapshotId);

        $this->assertSame(
            AccessDeviceConfigurationReadStatus::Partial,
            $device->configuration_read_status
        );

        $this->assertSame(
            '2026-07-15 08:30:00',
            data_get(
                $device->current_configuration,
                'device.date_time'
            )
        );

        $this->assertSame(
            'FIRMWARE-ANTERIOR',
            data_get(
                $device->current_configuration,
                'device.firmware_version'
            )
        );

        $this->assertSame(
            'Close',
            data_get(
                $device->current_configuration,
                'door.current_status'
            )
        );

        $this->assertSame(
            3,
            data_get(
                $device->current_configuration,
                'door.relay_activation_seconds'
            )
        );

        $this->assertTrue(
            data_get(
                $device->current_configuration,
                'alarms.door_open_enabled'
            )
        );

        $this->assertSame(
            AccessDeviceCapabilityStatus::Supported->value,
            data_get(
                $device->capabilities,
                'device.date_time'
            )
        );

        $this->assertSame(
            AccessDeviceCapabilityStatus::Supported->value,
            data_get(
                $device->capabilities,
                'device.firmware_version'
            )
        );

        $this->assertSame(
            AccessDeviceCapabilityStatus::Supported->value,
            data_get(
                $device->capabilities,
                'door.relay_activation_seconds'
            )
        );

        $this->assertSame(
            AccessDeviceConfigurationReadStatus::Partial,
            $snapshot->status
        );

        $this->assertSame(
            '2026-07-15 08:30:00',
            data_get(
                $snapshot->configuration,
                'device.date_time'
            )
        );

        $this->assertNull(
            data_get(
                $snapshot->configuration,
                'device.firmware_version'
            )
        );

        $this->assertSame(
            'Close',
            data_get(
                $snapshot->configuration,
                'door.current_status'
            )
        );

        $this->assertNull(
            data_get(
                $snapshot->configuration,
                'door.relay_activation_seconds'
            )
        );

        $this->assertNull(
            $snapshot->firmware_version
        );

        $this->assertSame(
            [
                'Versão do firmware: resposta não disponível.',
            ],
            data_get(
                $snapshot->sanitized_response,
                '_warnings'
            )
        );
    }

    public function test_failed_read_preserves_the_last_successful_configuration(): void
    {
        [
            $device,
            $user,
        ] = $this->createDeviceContext();

        $previousConfiguration = [
            'door' => [
                'current_status' => 'Open',
            ],
        ];

        $device->forceFill([
            'current_configuration' => $previousConfiguration,
            'capabilities' => [
                'door' => [
                    'current_status' => AccessDeviceCapabilityStatus::Supported
                        ->value,
                ],
            ],
        ])->saveQuietly();

        $this->app->instance(
            AccessDeviceConfigurationReader::class,
            new class implements AccessDeviceConfigurationReader
            {
                public function read(
                    AccessDeviceConnectionData $connection
                ): AccessDeviceConfigurationReadResult {
                    throw new AccessDeviceConfigurationReadException(
                        'Não foi possível conectar ao equipamento.',
                        'current_time'
                    );
                }
            }
        );

        try {
            app(
                ReadAccessDeviceConfigurationUseCase::class
            )->execute(
                new ReadAccessDeviceConfigurationCommand(
                    deviceId: $device->id,
                    requestedByUserId: $user->id,
                )
            );

            $this->fail(
                'Era esperada uma falha de leitura.'
            );
        } catch (
            ReadAccessDeviceConfigurationException $exception
        ) {
            $this->assertNotNull(
                $exception->snapshotId
            );
        }

        $device->refresh();

        $this->assertSame(
            AccessDeviceConfigurationReadStatus::Failed,
            $device->configuration_read_status
        );

        $this->assertSame(
            $previousConfiguration,
            $device->current_configuration
        );

        $snapshot =
            AccessDeviceConfigurationSnapshotRecord::query()
                ->latest('created_at')
                ->firstOrFail();

        $this->assertSame(
            AccessDeviceConfigurationReadStatus::Failed,
            $snapshot->status
        );

        $this->assertNull(
            $snapshot->configuration
        );

        $this->assertSame(
            'Não foi possível conectar ao equipamento.',
            $snapshot->message
        );
    }

    /**
     * @return array{
     *     0: AccessDeviceRecord,
     *     1: User
     * }
     */
    private function createDeviceContext(): array
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO DEMONSTRAÇÃO',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE DEMONSTRAÇÃO LTDA',
                'display_name' => 'UNIDADE DEMONSTRAÇÃO',
                'unit_code' => 'DEM-01',
            ]);

        $user = User::factory()->create();

        $device =
            AccessDeviceRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'code' => 'FAC-ENT-01',
                'name' => 'Facial entrada 01',
                'provider' => 'intelbras',
                'model' => 'SS 3532 MF W',
                'ip_address' => '192.168.1.201',
                'port' => 80,
                'protocol' => 'http',
                'auth_type' => 'digest',
                'credential_username' => 'admin',
                'credential_password' => 'synthetic-secret',
                'direction' => AccessDeviceDirection::Entry,
                'status' => AccessDeviceStatus::Active,
                'settings' => [
                    'verify_tls' => false,
                ],
            ]);

        return [
            $device,
            $user,
        ];
    }
}
