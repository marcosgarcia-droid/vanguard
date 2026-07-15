<?php

namespace Tests\Unit\Database\Seeders;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventCollectionMode;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Database\Seeders\VanguardAccessDeviceDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VanguardAccessDeviceDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_four_isolated_synthetic_devices_without_network_or_credentials(): void
    {
        $this->seed(
            VanguardAccessDeviceDemoSeeder::class
        );

        $tenant = TenantRecord::query()
            ->where(
                'name',
                'VANGUARD LABORATÓRIO SINTÉTICO'
            )
            ->firstOrFail();

        $organization =
            OrganizationRecord::query()
                ->where(
                    'tenant_id',
                    $tenant->id
                )
                ->where(
                    'unit_code',
                    'LAB-AC-01'
                )
                ->firstOrFail();

        $devices = AccessDeviceRecord::query()
            ->where(
                'tenant_id',
                $tenant->id
            )
            ->where(
                'organization_id',
                $organization->id
            )
            ->orderBy('code')
            ->get();

        $this->assertCount(
            4,
            $devices
        );

        $this->assertSame(
            [
                'FAC-SIM-ENT-01',
                'FAC-SIM-ENT-02',
                'FAC-SIM-SAI-01',
                'FAC-SIM-SAI-02',
            ],
            $devices->pluck('code')->all()
        );

        $this->assertSame(
            2,
            $devices
                ->where(
                    'direction',
                    AccessDeviceDirection::Entry
                )
                ->count()
        );

        $this->assertSame(
            2,
            $devices
                ->where(
                    'direction',
                    AccessDeviceDirection::Exit
                )
                ->count()
        );

        foreach ($devices as $device) {
            $this->assertSame(
                'simulator',
                $device->provider
            );

            $this->assertSame(
                AccessDeviceStatus::Active,
                $device->status
            );

            $this->assertNull(
                $device->ip_address
            );

            $this->assertNull(
                $device->port
            );

            $this->assertNull(
                $device->credential_username
            );

            $this->assertNull(
                $device->credential_password
            );

            $this->assertSame(
                AccessEventCollectionMode::Disabled
                    ->value,
                data_get(
                    $device->settings,
                    'event_collection_mode'
                )
            );

            $this->assertStringContainsString(
                'sintético',
                mb_strtolower(
                    (string) $device->notes
                )
            );
        }

        $this->assertSame(
            'success',
            data_get(
                $devices->firstWhere(
                    'code',
                    'FAC-SIM-ENT-01'
                )?->settings,
                'simulator_scenario'
            )
        );

        $this->assertSame(
            'partial',
            data_get(
                $devices->firstWhere(
                    'code',
                    'FAC-SIM-ENT-02'
                )?->settings,
                'simulator_scenario'
            )
        );

        $this->assertSame(
            'failed',
            data_get(
                $devices->firstWhere(
                    'code',
                    'FAC-SIM-SAI-02'
                )?->settings,
                'simulator_scenario'
            )
        );
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(
            VanguardAccessDeviceDemoSeeder::class
        );

        $firstIds = AccessDeviceRecord::query()
            ->where(
                'provider',
                'simulator'
            )
            ->whereIn(
                'code',
                [
                    'FAC-SIM-ENT-01',
                    'FAC-SIM-ENT-02',
                    'FAC-SIM-SAI-01',
                    'FAC-SIM-SAI-02',
                ]
            )
            ->orderBy('code')
            ->pluck('id')
            ->all();

        $this->seed(
            VanguardAccessDeviceDemoSeeder::class
        );

        $secondIds = AccessDeviceRecord::query()
            ->where(
                'provider',
                'simulator'
            )
            ->whereIn(
                'code',
                [
                    'FAC-SIM-ENT-01',
                    'FAC-SIM-ENT-02',
                    'FAC-SIM-SAI-01',
                    'FAC-SIM-SAI-02',
                ]
            )
            ->orderBy('code')
            ->pluck('id')
            ->all();

        $this->assertCount(
            4,
            $secondIds
        );

        $this->assertSame(
            $firstIds,
            $secondIds
        );
    }
}
