<?php

declare(strict_types=1);

namespace App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\SimulatedIntelbrasFacialCredentialSynchronizationScenario;

final class VisitorFacialCredentialSynchronizationExecutionEnvironment
{
    public static function isReady(): bool
    {
        return self::reason() === null;
    }

    public static function reason(): ?string
    {
        if (self::provider() !== 'simulator') {
            return 'A execução facial sintética está desativada.';
        }

        if (! self::simulatorEnabled()) {
            return 'O simulador facial não está habilitado.';
        }

        if (! self::environmentIsAllowed()) {
            return 'O ambiente atual não permite a execução do simulador facial.';
        }

        if (self::scenario() === null) {
            return 'O cenário sintético não foi configurado ou não é reconhecido.';
        }

        return null;
    }

    public static function scenario(): ?SimulatedIntelbrasFacialCredentialSynchronizationScenario
    {
        $scenario = self::normalize(
            config(
                'intelbras_facial_synchronization.simulator.scenario'
            )
        );

        if ($scenario === '') {
            return null;
        }

        return SimulatedIntelbrasFacialCredentialSynchronizationScenario::tryFrom(
            $scenario
        );
    }

    /**
     * @return array{
     *     ready: bool,
     *     provider: string,
     *     simulator_enabled: bool,
     *     environment: string,
     *     environment_allowed: bool,
     *     scenario: string|null,
     *     reason: string|null
     * }
     */
    public static function toSafeArray(): array
    {
        return [
            'ready' => self::isReady(),
            'provider' => self::provider(),
            'simulator_enabled' => self::simulatorEnabled(),
            'environment' => self::environment(),
            'environment_allowed' => self::environmentIsAllowed(),
            'scenario' => self::scenario()?->value,
            'reason' => self::reason(),
        ];
    }

    private static function provider(): string
    {
        return self::normalize(
            config(
                'intelbras_facial_synchronization.provider',
                'disabled'
            )
        );
    }

    private static function simulatorEnabled(): bool
    {
        return (bool) config(
            'intelbras_facial_synchronization.simulator.enabled',
            false
        );
    }

    private static function environment(): string
    {
        return self::normalize(
            app()->environment()
        );
    }

    private static function environmentIsAllowed(): bool
    {
        $environment = self::environment();

        if ($environment === '') {
            return false;
        }

        $allowed = config(
            'intelbras_facial_synchronization.simulator.allowed_environments',
            []
        );

        if (! is_array($allowed)) {
            return false;
        }

        foreach ($allowed as $candidate) {
            if (
                is_string($candidate)
                && self::normalize($candidate)
                    === $environment
            ) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(
        mixed $value
    ): string {
        return is_string($value)
            ? strtolower(trim($value))
            : '';
    }
}
