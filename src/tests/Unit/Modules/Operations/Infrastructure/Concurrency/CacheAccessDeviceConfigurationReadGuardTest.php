<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Concurrency;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadGuardException;
use App\Modules\Operations\Infrastructure\Concurrency\CacheAccessDeviceConfigurationReadGuard;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheAccessDeviceConfigurationReadGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'cache.default',
            'array'
        );

        config()->set(
            'access_control.read_lock_seconds',
            60
        );

        config()->set(
            'access_control.read_min_interval_seconds',
            30
        );

        Cache::store('array')->flush();
    }

    public function test_it_blocks_a_second_concurrent_read_for_the_same_device(): void
    {
        $guard = app(
            CacheAccessDeviceConfigurationReadGuard::class
        );

        $firstLease = $guard->acquire(
            'device-a'
        );

        try {
            $guard->acquire('device-a');

            $this->fail(
                'Era esperado o bloqueio da leitura concorrente.'
            );
        } catch (
            AccessDeviceConfigurationReadGuardException $exception
        ) {
            $this->assertStringContainsString(
                'leitura em andamento',
                $exception->getMessage()
            );
        } finally {
            $firstLease->release();
        }
    }

    public function test_it_allows_different_devices_to_be_read_independently(): void
    {
        $guard = app(
            CacheAccessDeviceConfigurationReadGuard::class
        );

        $firstLease = $guard->acquire(
            'device-a'
        );

        $secondLease = $guard->acquire(
            'device-b'
        );

        try {
            $this->addToAssertionCount(1);
        } finally {
            $secondLease->release();
            $firstLease->release();
        }
    }

    public function test_it_starts_the_minimum_interval_only_when_the_reader_is_called(): void
    {
        $guard = app(
            CacheAccessDeviceConfigurationReadGuard::class
        );

        $validationOnlyLease =
            $guard->acquire('device-a');

        $validationOnlyLease->release();

        $readLease = $guard->acquire(
            'device-a'
        );

        $readLease->markReaderCalled();
        $readLease->release();

        try {
            $guard->acquire('device-a');

            $this->fail(
                'Era esperado o bloqueio pelo intervalo mínimo.'
            );
        } catch (
            AccessDeviceConfigurationReadGuardException $exception
        ) {
            $this->assertStringContainsString(
                'realizada recentemente',
                $exception->getMessage()
            );
        }
    }

    public function test_releasing_a_lease_allows_a_new_execution_when_no_reader_was_called(): void
    {
        $guard = app(
            CacheAccessDeviceConfigurationReadGuard::class
        );

        $firstLease = $guard->acquire(
            'device-a'
        );

        $firstLease->release();

        $secondLease = $guard->acquire(
            'device-a'
        );

        try {
            $this->addToAssertionCount(1);
        } finally {
            $secondLease->release();
        }
    }
}
