<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use Tests\TestCase;

final class IntelbrasFacialSynchronizationConfigurationTest extends TestCase
{
    public function test_it_is_fail_closed_by_default(): void
    {
        $this->assertSame(
            'disabled',
            config(
                'intelbras_facial_synchronization.provider'
            )
        );

        $this->assertFalse(
            (bool) config(
                'intelbras_facial_synchronization.simulator.enabled'
            )
        );

        $this->assertNull(
            config(
                'intelbras_facial_synchronization.simulator.scenario'
            )
        );
    }

    public function test_simulator_environments_are_fixed_in_code(): void
    {
        $this->assertSame(
            [
                'local',
                'testing',
            ],
            config(
                'intelbras_facial_synchronization.simulator.allowed_environments'
            )
        );
    }

    public function test_environment_example_documents_safe_defaults(): void
    {
        $contents = file_get_contents(
            base_path('.env.example')
        );

        $this->assertIsString($contents);

        $this->assertStringContainsString(
            'VANGUARD_INTELBRAS_FACIAL_SYNCHRONIZATION_PROVIDER=disabled',
            $contents
        );

        $this->assertStringContainsString(
            'VANGUARD_INTELBRAS_FACIAL_SYNCHRONIZATION_SIMULATOR_ENABLED=false',
            $contents
        );

        $this->assertStringContainsString(
            'VANGUARD_INTELBRAS_FACIAL_SYNCHRONIZATION_SIMULATOR_SCENARIO=',
            $contents
        );
    }

    public function test_phpunit_forces_safe_defaults(): void
    {
        $contents = file_get_contents(
            base_path('phpunit.xml')
        );

        $this->assertIsString($contents);

        $this->assertStringContainsString(
            'name="VANGUARD_INTELBRAS_FACIAL_SYNCHRONIZATION_PROVIDER" value="disabled"',
            $contents
        );

        $this->assertStringContainsString(
            'name="VANGUARD_INTELBRAS_FACIAL_SYNCHRONIZATION_SIMULATOR_ENABLED" value="false"',
            $contents
        );

        $this->assertStringContainsString(
            'name="VANGUARD_INTELBRAS_FACIAL_SYNCHRONIZATION_SIMULATOR_SCENARIO" value=""',
            $contents
        );
    }
}
