<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl;

use App\Modules\Operations\Application\AccessControl\AccessControlRuntime;
use App\Modules\Operations\Domain\AccessControl\AccessControlMode;
use Tests\TestCase;

class AccessControlRuntimeTest extends TestCase
{
    public function test_observer_mode_blocks_device_writes_even_when_flag_is_enabled(): void
    {
        config()->set(
            'access_control.mode',
            AccessControlMode::Observer->value
        );

        config()->set(
            'access_control.writes_enabled',
            true
        );

        $runtime = app(AccessControlRuntime::class);

        $this->assertSame(
            AccessControlMode::Observer,
            $runtime->mode()
        );

        $this->assertTrue(
            $runtime->writesConfigured()
        );

        $this->assertFalse(
            $runtime->allowsWrites()
        );
    }

    public function test_primary_mode_requires_the_write_flag(): void
    {
        config()->set(
            'access_control.mode',
            AccessControlMode::Primary->value
        );

        config()->set(
            'access_control.writes_enabled',
            false
        );

        $runtime = app(AccessControlRuntime::class);

        $this->assertFalse(
            $runtime->allowsWrites()
        );

        config()->set(
            'access_control.writes_enabled',
            true
        );

        $this->assertTrue(
            $runtime->allowsWrites()
        );
    }

    public function test_unknown_mode_falls_back_to_observer(): void
    {
        config()->set(
            'access_control.mode',
            'invalid-mode'
        );

        $this->assertSame(
            AccessControlMode::Observer,
            app(AccessControlRuntime::class)->mode()
        );
    }

    public function test_reads_require_the_explicit_environment_flag(): void
    {
        config()->set(
            'access_control.reads_enabled',
            false
        );

        $this->assertFalse(
            app(AccessControlRuntime::class)
                ->allowsReads()
        );

        config()->set(
            'access_control.reads_enabled',
            true
        );

        $this->assertTrue(
            app(AccessControlRuntime::class)
                ->allowsReads()
        );
    }

    public function test_it_exposes_only_configured_allowed_networks(): void
    {
        config()->set(
            'access_control.allowed_cidrs',
            [
                '192.168.50.0/24',
                '10.20.0.0/16',
            ]
        );

        $this->assertSame(
            [
                '192.168.50.0/24',
                '10.20.0.0/16',
            ],
            app(AccessControlRuntime::class)
                ->allowedCidrs()
        );
    }
}
