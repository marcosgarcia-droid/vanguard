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

    public function readsConfigured(): bool
    {
        return filter_var(
            config(
                'access_control.reads_enabled',
                false
            ),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function allowsReads(): bool
    {
        return $this->readsConfigured();
    }

    /**
     * @return array<int, string>
     */
    public function allowedCidrs(): array
    {
        $cidrs = config(
            'access_control.allowed_cidrs',
            []
        );

        if (is_string($cidrs)) {
            $cidrs = explode(',', $cidrs);
        }

        if (! is_array($cidrs)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static fn (mixed $cidr): string => trim((string) $cidr),
                    $cidrs
                )
            )
        );
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
