<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceCapabilityStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationSource;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceConfigurationSnapshotRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessDeviceConfigurationSnapshotRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_preserves_a_device_configuration_read_snapshot(): void
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
            ]);

        $device =
            AccessDeviceRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'code' => 'FAC-ENT-01',
                'name' => 'Facial entrada 01',
                'provider' => 'intelbras',
                'direction' => AccessDeviceDirection::Entry,
                'status' => AccessDeviceStatus::Active,
            ]);

        $configuration = [
            'alarms' => [
                'door_open_enabled' => true,
                'break_in_enabled' => true,
            ],
            'door' => [
                'relay_activation_seconds' => 5,
            ],
        ];

        $capabilities = [
            'alarms' => [
                'door_open_enabled' => AccessDeviceCapabilityStatus::Supported
                    ->value,
            ],
            'device' => [
                'usb_disabled' => AccessDeviceCapabilityStatus::Unknown
                    ->value,
            ],
        ];

        $snapshot =
            AccessDeviceConfigurationSnapshotRecord::query()
                ->create([
                    'access_device_id' => $device->id,
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'source' => AccessDeviceConfigurationSource::Manual,
                    'status' => AccessDeviceConfigurationReadStatus::Success,
                    'device_model' => 'SS 3532 MF W',
                    'firmware_version' => 'DEMO-FIRMWARE',
                    'configuration' => $configuration,
                    'capabilities' => $capabilities,
                    'sanitized_response' => [
                        'source' => 'synthetic-test',
                    ],
                    'configuration_hash' => hash(
                        'sha256',
                        json_encode($configuration)
                    ),
                    'read_at' => now(),
                    'duration_ms' => 250,
                    'message' => 'Leitura sintética concluída.',
                ]);

        $device->update([
            'current_configuration' => $configuration,
            'capabilities' => $capabilities,
            'configuration_read_at' => $snapshot->read_at,
            'configuration_read_status' => AccessDeviceConfigurationReadStatus::Success,
            'configuration_read_message' => 'Leitura sintética concluída.',
        ]);

        $loadedDevice =
            AccessDeviceRecord::query()
                ->with('configurationSnapshots')
                ->findOrFail($device->id);

        $loadedSnapshot =
            $loadedDevice
                ->configurationSnapshots
                ->firstOrFail();

        $this->assertSame(
            AccessDeviceConfigurationReadStatus::Success,
            $loadedDevice->configuration_read_status
        );

        $this->assertTrue(
            $loadedSnapshot->accessDevice->is(
                $device
            )
        );

        $this->assertSame(
            AccessDeviceConfigurationSource::Manual,
            $loadedSnapshot->source
        );

        $this->assertSame(
            true,
            data_get(
                $loadedDevice->current_configuration,
                'alarms.door_open_enabled'
            )
        );

        $this->assertSame(
            5,
            data_get(
                $loadedSnapshot->configuration,
                'door.relay_activation_seconds'
            )
        );

        $this->assertSame(
            AccessDeviceCapabilityStatus::Supported
                ->value,
            data_get(
                $loadedSnapshot->capabilities,
                'alarms.door_open_enabled'
            )
        );
    }
}
