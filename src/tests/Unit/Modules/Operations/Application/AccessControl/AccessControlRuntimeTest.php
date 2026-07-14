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
}
