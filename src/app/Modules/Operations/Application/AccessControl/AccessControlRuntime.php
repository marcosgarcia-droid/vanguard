<?php

namespace App\Modules\Operations\Application\AccessControl;

use App\Modules\Operations\Domain\AccessControl\AccessControlMode;

final class AccessControlRuntime
{
    public function mode(): AccessControlMode
    {
        return AccessControlMode::tryFrom(
            (string) config('access_control.mode', 'observer')
        ) ?? AccessControlMode::Observer;
    }

    public function writesConfigured(): bool
    {
        return filter_var(
            config('access_control.writes_enabled', false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function allowsWrites(): bool
    {
        return $this->writesConfigured()
            && $this->mode()->canWriteToDevices();
    }

    public function summary(): string
    {
        return $this->mode()->label()
            .' — '
            .$this->mode()->description();
    }
}
