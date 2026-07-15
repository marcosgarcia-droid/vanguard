<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\ReadAccessDeviceConfigurationCommand;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\ReadAccessDeviceConfigurationException;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\ReadAccessDeviceConfigurationUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceConfigurationSnapshotRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReadSimulatedAccessDeviceConfigurationUseCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('access_control.reads_enabled', false);
        config()->set('access_control.simulator_enabled', true);
        config()->set(
            'access_control.read_min_interval_seconds',
            0
        );

        Cache::store('array')->flush();
        Http::fake();
    }

    public function test_it_reads_a_simulated_device_while_real_reads_remain_disabled(): void
    {
        [$device, $user] =
            $this->createDeviceContext();

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
            AccessDeviceConfigurationReadStatus::Success,
            $result->status
        );

        $this->assertSame(
            AccessDeviceConfigurationReadStatus::Success,
            $device->configuration_read_status
        );

        $this->assertSame(
            'simulator',
            data_get(
                $device->current_configuration,
                'device.provider'
            )
        );

        $this->assertSame(
            'SIMULATOR-1.0.0',
            $snapshot->firmware_version
        );

        $this->assertSame(
            'success',
            data_get(
                $snapshot->sanitized_response,
                'simulator.scenario'
            )
        );

        Http::assertSentCount(0);
    }

    public function test_it_rejects_an_inactive_simulated_device(): void
    {
        [$device, $user] =
            $this->createDeviceContext();

        $device->forceFill([
            'status' => AccessDeviceStatus::Inactive,
        ])->saveQuietly();

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
                'Era esperado o bloqueio do dispositivo inativo.'
            );
        } catch (
            ReadAccessDeviceConfigurationException $exception
        ) {
            $this->assertNotNull(
                $exception->snapshotId
            );

            $this->assertStringContainsString(
                'precisa estar ativo',
                $exception->getMessage()
            );
        }

        $device->refresh();

        $this->assertSame(
            AccessDeviceConfigurationReadStatus::Failed,
            $device->configuration_read_status
        );

        Http::assertSentCount(0);
    }

    /**
     * @return array{AccessDeviceRecord, User}
     */
    private function createDeviceContext(): array
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO SINTÉTICO',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE SINTÉTICA LTDA',
                'display_name' => 'UNIDADE SINTÉTICA',
                'unit_code' => 'SIM-01',
            ]);

        $user = User::factory()->create();

        $device =
            AccessDeviceRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'code' => 'FAC-SIM-01',
                'name' => 'Facial simulado 01',
                'provider' => 'simulator',
                'model' => 'SIMULADOR LOCAL',
                'external_id' => 'synthetic-device-001',
                'ip_address' => '127.0.0.1',
                'port' => 1,
                'protocol' => 'http',
                'auth_type' => 'digest',
                'credential_username' => 'simulator',
                'credential_password' => 'synthetic-only',
                'direction' => AccessDeviceDirection::Entry,
                'status' => AccessDeviceStatus::Active,
                'settings' => [
                    'simulator_scenario' => 'success',
                    'event_collection_mode' => 'disabled',
                ],
            ]);

        return [$device, $user];
    }
}
