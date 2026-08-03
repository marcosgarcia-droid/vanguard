<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

final readonly class ConfiguredIntelbrasFacialCredentialSynchronizerResolver implements IntelbrasFacialCredentialSynchronizerResolver
{
    /**
     * @param  array<array-key, mixed>  $simulatorAllowedEnvironments
     */
    public function __construct(
        private string $environment,
        private ?string $provider,
        private bool $simulatorEnabled,
        private array $simulatorAllowedEnvironments,
        private ?string $simulatorScenario,
    ) {}

    public function resolve(): IntelbrasFacialCredentialSynchronizer
    {
        if ($this->normalize($this->provider) !== 'simulator') {
            return $this->disabled();
        }

        if (! $this->simulatorEnabled) {
            return $this->disabled();
        }

        if (! $this->environmentAllowsSimulator()) {
            return $this->disabled();
        }

        $scenario =
            SimulatedIntelbrasFacialCredentialSynchronizationScenario::tryFrom(
                $this->normalize($this->simulatorScenario)
            );

        if ($scenario === null) {
            return $this->disabled();
        }

        return new SimulatedIntelbrasFacialCredentialSynchronizer(
            $scenario
        );
    }

    private function environmentAllowsSimulator(): bool
    {
        $environment = $this->normalize(
            $this->environment
        );

        if ($environment === '') {
            return false;
        }

        foreach (
            $this->simulatorAllowedEnvironments as $allowedEnvironment
        ) {
            if (! is_string($allowedEnvironment)) {
                continue;
            }

            if (
                $this->normalize($allowedEnvironment)
                === $environment
            ) {
                return true;
            }
        }

        return false;
    }

    private function disabled(): IntelbrasFacialCredentialSynchronizer
    {
        return new DisabledIntelbrasFacialCredentialSynchronizer;
    }

    private function normalize(?string $value): string
    {
        return strtolower(
            trim((string) $value)
        );
    }
}
