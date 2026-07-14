<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\DeviceConfiguration;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\AccessDeviceConfigurationCatalog;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationOperation;
use PHPUnit\Framework\TestCase;

class AccessDeviceConfigurationCatalogTest extends TestCase
{
    public function test_it_catalogs_documented_device_settings_statuses_and_commands(): void
    {
        $this->assertGreaterThanOrEqual(
            40,
            count(
                AccessDeviceConfigurationCatalog::definitions()
            )
        );

        $doorAlarm =
            AccessDeviceConfigurationCatalog::find(
                'alarms.door_open_enabled'
            );

        $this->assertNotNull($doorAlarm);

        $this->assertSame(
            AccessDeviceConfigurationOperation::Configuration,
            $doorAlarm['operation']
        );

        $restart =
            AccessDeviceConfigurationCatalog::find(
                'device.restart'
            );

        $this->assertNotNull($restart);

        $this->assertSame(
            AccessDeviceConfigurationOperation::Command,
            $restart['operation']
        );

        $doorStatus =
            AccessDeviceConfigurationCatalog::find(
                'door.current_status'
            );

        $this->assertNotNull($doorStatus);

        $this->assertSame(
            AccessDeviceConfigurationOperation::Status,
            $doorStatus['operation']
        );
    }

    public function test_it_groups_configuration_items_by_functional_category(): void
    {
        $grouped =
            AccessDeviceConfigurationCatalog::grouped();

        $this->assertArrayHasKey(
            'device',
            $grouped
        );

        $this->assertArrayHasKey(
            'turnstile',
            $grouped
        );

        $this->assertArrayHasKey(
            'door',
            $grouped
        );

        $this->assertArrayHasKey(
            'face',
            $grouped
        );

        $this->assertNotEmpty(
            $grouped['door']['items']
        );
    }
}
