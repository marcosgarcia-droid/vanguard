<?php

namespace App\Modules\Operations\Infrastructure\Concurrency;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadGuard;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadGuardException;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadLease;
use Illuminate\Support\Facades\Cache;
use Throwable;

final readonly class CacheAccessDeviceConfigurationReadGuard implements AccessDeviceConfigurationReadGuard
{
    public function acquire(
        string $deviceId
    ): AccessDeviceConfigurationReadLease {
        $fingerprint = hash(
            'sha256',
            $deviceId
        );

        $lock = Cache::lock(
            $this->lockKey($fingerprint),
            $this->lockSeconds()
        );

        if (! $lock->get()) {
            throw new AccessDeviceConfigurationReadGuardException(
                'Já existe uma leitura em andamento para este dispositivo. Aguarde a conclusão antes de tentar novamente.'
            );
        }

        try {
            $minimumIntervalSeconds =
                $this->minimumIntervalSeconds();

            $minimumIntervalKey =
                $this->minimumIntervalKey(
                    $fingerprint
                );

            if (
                $minimumIntervalSeconds > 0
                && Cache::has(
                    $minimumIntervalKey
                )
            ) {
                throw new AccessDeviceConfigurationReadGuardException(
                    'Uma leitura deste dispositivo foi realizada recentemente. Aguarde alguns segundos antes de tentar novamente.'
                );
            }

            return new CacheAccessDeviceConfigurationReadLease(
                cache: Cache::store(),
                lock: $lock,
                minimumIntervalKey: $minimumIntervalKey,
                minimumIntervalSeconds: $minimumIntervalSeconds,
            );
        } catch (Throwable $exception) {
            $lock->release();

            throw $exception;
        }
    }

    private function lockKey(
        string $fingerprint
    ): string {
        return 'vanguard:access-control:configuration-read:lock:'
            .$fingerprint;
    }

    private function minimumIntervalKey(
        string $fingerprint
    ): string {
        return 'vanguard:access-control:configuration-read:interval:'
            .$fingerprint;
    }

    private function lockSeconds(): int
    {
        return max(
            60,
            (int) config(
                'access_control.read_lock_seconds',
                60
            )
        );
    }

    private function minimumIntervalSeconds(): int
    {
        return max(
            0,
            (int) config(
                'access_control.read_min_interval_seconds',
                30
            )
        );
    }
}
