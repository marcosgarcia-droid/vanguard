<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadGuard;
use App\Modules\Operations\Infrastructure\Concurrency\CacheAccessDeviceConfigurationReadGuard;
use Tests\TestCase;

class AccessDeviceConfigurationReadGuardBindingTest extends TestCase
{
    public function test_it_resolves_the_cache_based_guard(): void
    {
        $this->assertInstanceOf(
            CacheAccessDeviceConfigurationReadGuard::class,
            app(
                AccessDeviceConfigurationReadGuard::class
            )
        );
    }
}
