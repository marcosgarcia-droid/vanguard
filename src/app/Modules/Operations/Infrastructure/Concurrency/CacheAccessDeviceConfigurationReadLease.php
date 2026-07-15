<?php

namespace App\Modules\Operations\Infrastructure\Concurrency;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadLease;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository;

final class CacheAccessDeviceConfigurationReadLease implements AccessDeviceConfigurationReadLease
{
    private bool $readerCalled = false;

    private bool $released = false;

    public function __construct(
        private readonly Repository $cache,
        private readonly Lock $lock,
        private readonly string $minimumIntervalKey,
        private readonly int $minimumIntervalSeconds,
    ) {}

    public function markReaderCalled(): void
    {
        if (
            $this->released
            || $this->readerCalled
        ) {
            return;
        }

        $this->readerCalled = true;

        if ($this->minimumIntervalSeconds <= 0) {
            return;
        }

        $this->cache->put(
            $this->minimumIntervalKey,
            true,
            $this->minimumIntervalSeconds
        );
    }

    public function release(): void
    {
        if ($this->released) {
            return;
        }

        $this->lock->release();

        $this->released = true;
    }
}
