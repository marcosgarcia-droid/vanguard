<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\ConfiguredIntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\DocumentedIntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityResolutionStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\SimulatedIntelbrasFacialCredentialCompatibilityCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ConfiguredIntelbrasFacialCredentialCompatibilityCatalogTest extends TestCase
{
    public function test_it_selects_the_simulated_catalog_only_with_all_guards(): void
    {
        $resolution = $this->catalog(
            environment: 'local',
            provider: 'simulator',
            enabled: true,
            allowedEnvironments: [
                'local',
                'testing',
            ],
            scenario: 'succeeded',
        )->resolve(
            model: SimulatedIntelbrasFacialCredentialCompatibilityCatalog::MODEL,

            firmware: SimulatedIntelbrasFacialCredentialCompatibilityCatalog::FIRMWARE,
        );

        self::assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::Compatible,
            $resolution->status
        );
    }

    /**
     * @return iterable<string, array{
     *     0: string,
     *     1: string|null,
     *     2: bool,
     *     3: array<array-key, mixed>,
     *     4: string|null
     * }>
     */
    public static function blockedConfigurations(): iterable
    {
        yield 'provider disabled' => [
            'local',
            'disabled',
            true,
            ['local', 'testing'],
            'succeeded',
        ];

        yield 'provider físico desconhecido' => [
            'local',
            'intelbras',
            true,
            ['local', 'testing'],
            'succeeded',
        ];

        yield 'simulador desligado' => [
            'local',
            'simulator',
            false,
            ['local', 'testing'],
            'succeeded',
        ];

        yield 'ambiente não autorizado' => [
            'production',
            'simulator',
            true,
            ['local', 'testing'],
            'succeeded',
        ];

        yield 'cenário ausente' => [
            'local',
            'simulator',
            true,
            ['local', 'testing'],
            null,
        ];

        yield 'cenário inválido' => [
            'local',
            'simulator',
            true,
            ['local', 'testing'],
            'unknown',
        ];
    }

    /**
     * @param  array<array-key, mixed>  $allowedEnvironments
     */
    #[DataProvider('blockedConfigurations')]
    public function test_it_falls_back_to_the_documented_catalog(
        string $environment,
        ?string $provider,
        bool $enabled,
        array $allowedEnvironments,
        ?string $scenario,
    ): void {
        $resolution = $this->catalog(
            environment: $environment,
            provider: $provider,
            enabled: $enabled,
            allowedEnvironments: $allowedEnvironments,
            scenario: $scenario,
        )->resolve(
            model: SimulatedIntelbrasFacialCredentialCompatibilityCatalog::MODEL,

            firmware: SimulatedIntelbrasFacialCredentialCompatibilityCatalog::FIRMWARE,
        );

        self::assertSame(
            IntelbrasFacialCredentialCompatibilityResolutionStatus::UnknownModel,
            $resolution->status
        );
    }

    /**
     * @param  array<array-key, mixed>  $allowedEnvironments
     */
    private function catalog(
        string $environment,
        ?string $provider,
        bool $enabled,
        array $allowedEnvironments,
        ?string $scenario,
    ): ConfiguredIntelbrasFacialCredentialCompatibilityCatalog {
        return new ConfiguredIntelbrasFacialCredentialCompatibilityCatalog(
            environment: $environment,
            provider: $provider,
            simulatorEnabled: $enabled,
            simulatorAllowedEnvironments: $allowedEnvironments,
            simulatorScenario: $scenario,

            documentedCatalog: new DocumentedIntelbrasFacialCredentialCompatibilityCatalog,

            simulatedCatalog: new SimulatedIntelbrasFacialCredentialCompatibilityCatalog,
        );
    }
}
