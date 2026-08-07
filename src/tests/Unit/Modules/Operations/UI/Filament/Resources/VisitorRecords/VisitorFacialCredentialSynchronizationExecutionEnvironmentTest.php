<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\SimulatedIntelbrasFacialCredentialSynchronizationScenario;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions\VisitorFacialCredentialSynchronizationExecutionEnvironment;
use Tests\TestCase;

final class VisitorFacialCredentialSynchronizationExecutionEnvironmentTest extends TestCase
{
    public function test_it_is_fail_closed_by_default(): void
    {
        config()->set(
            'intelbras_facial_synchronization.provider',
            'disabled'
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.enabled',
            false
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.scenario',
            null
        );

        self::assertFalse(
            VisitorFacialCredentialSynchronizationExecutionEnvironment::isReady()
        );

        self::assertSame(
            'A execução facial sintética está desativada.',
            VisitorFacialCredentialSynchronizationExecutionEnvironment::reason()
        );
    }

    public function test_it_requires_all_simulator_guards(): void
    {
        config()->set(
            'intelbras_facial_synchronization.provider',
            'simulator'
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.enabled',
            true
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.allowed_environments',
            ['testing']
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.scenario',
            'succeeded'
        );

        self::assertTrue(
            VisitorFacialCredentialSynchronizationExecutionEnvironment::isReady()
        );

        self::assertNull(
            VisitorFacialCredentialSynchronizationExecutionEnvironment::reason()
        );

        self::assertSame(
            SimulatedIntelbrasFacialCredentialSynchronizationScenario::Succeeded,
            VisitorFacialCredentialSynchronizationExecutionEnvironment::scenario()
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.allowed_environments',
            ['local']
        );

        self::assertFalse(
            VisitorFacialCredentialSynchronizationExecutionEnvironment::isReady()
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.allowed_environments',
            ['testing']
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.scenario',
            'unknown'
        );

        self::assertFalse(
            VisitorFacialCredentialSynchronizationExecutionEnvironment::isReady()
        );
    }

    public function test_safe_array_exposes_no_credentials_or_photo_data(): void
    {
        config()->set(
            'intelbras_facial_synchronization.provider',
            'simulator'
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.enabled',
            true
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.allowed_environments',
            ['testing']
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.scenario',
            'duplicate_photo'
        );

        $safe = VisitorFacialCredentialSynchronizationExecutionEnvironment::toSafeArray();

        self::assertTrue(
            $safe['ready']
        );

        self::assertSame(
            'duplicate_photo',
            $safe['scenario']
        );

        $encoded = json_encode(
            $safe,
            JSON_THROW_ON_ERROR
        );

        foreach ([
            'password',
            'credential',
            'token',
            'photo_path',
            'sha256',
            'base64',
        ] as $prohibited) {
            self::assertStringNotContainsString(
                $prohibited,
                strtolower($encoded)
            );
        }
    }
}
