<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentAccessDeviceConfigurationReadRepository;
use Tests\TestCase;

class AccessDeviceConfigurationReadRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_eloquent_repository(): void
    {
        $this->assertInstanceOf(
            EloquentAccessDeviceConfigurationReadRepository::class,
            app(
                AccessDeviceConfigurationReadRepository::class
            )
        );
    }
}
