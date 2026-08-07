<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

final readonly class ConfiguredIntelbrasFacialCredentialCompatibilityCatalog implements IntelbrasFacialCredentialCompatibilityCatalog
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
        private IntelbrasFacialCredentialCompatibilityCatalog $documentedCatalog,
        private IntelbrasFacialCredentialCompatibilityCatalog $simulatedCatalog,
    ) {}

    public function resolve(
        ?string $model,
        ?string $firmware,
    ): IntelbrasFacialCredentialCompatibilityResolution {
        $catalog = $this->simulatorIsAllowed()
            ? $this->simulatedCatalog
            : $this->documentedCatalog;

        return $catalog->resolve(
            model: $model,
            firmware: $firmware,
        );
    }

    private function simulatorIsAllowed(): bool
    {
        if (
            $this->normalize(
                $this->provider
            ) !== 'simulator'
        ) {
            return false;
        }

        if (! $this->simulatorEnabled) {
            return false;
        }

        if (! $this->environmentAllowsSimulator()) {
            return false;
        }

        return SimulatedIntelbrasFacialCredentialSynchronizationScenario::tryFrom(
            $this->normalize(
                $this->simulatorScenario
            )
        ) !== null;
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
                $this->normalize(
                    $allowedEnvironment
                ) === $environment
            ) {
                return true;
            }
        }

        return false;
    }

    private function normalize(
        ?string $value
    ): string {
        return strtolower(
            trim(
                (string) $value
            )
        );
    }
}
