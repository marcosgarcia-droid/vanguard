<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationRepository;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationRepository;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\ConfiguredIntelbrasFacialCredentialSynchronizerResolver;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\DocumentedIntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizerResolver;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentCreateFacialCredentialSynchronizationRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentExecuteFacialCredentialSynchronizationRepository;
use Illuminate\Support\ServiceProvider;

final class IntelbrasFacialSynchronizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            IntelbrasFacialCredentialCompatibilityCatalog::class,
            DocumentedIntelbrasFacialCredentialCompatibilityCatalog::class
        );

        $this->app->bind(
            CreateFacialCredentialSynchronizationRepository::class,
            EloquentCreateFacialCredentialSynchronizationRepository::class
        );

        $this->app->bind(
            ExecuteFacialCredentialSynchronizationRepository::class,
            EloquentExecuteFacialCredentialSynchronizationRepository::class
        );

        $this->app->bind(
            IntelbrasFacialCredentialSynchronizerResolver::class,
            function ($app): IntelbrasFacialCredentialSynchronizerResolver {
                $provider = $app['config']->get(
                    'intelbras_facial_synchronization.provider'
                );

                $allowedEnvironments = $app['config']->get(
                    'intelbras_facial_synchronization.simulator.allowed_environments',
                    []
                );

                $scenario = $app['config']->get(
                    'intelbras_facial_synchronization.simulator.scenario'
                );

                return new ConfiguredIntelbrasFacialCredentialSynchronizerResolver(
                    environment: (string) $app->environment(),
                    provider: is_string($provider)
                        ? $provider
                        : null,
                    simulatorEnabled: (bool) $app['config']->get(
                        'intelbras_facial_synchronization.simulator.enabled',
                        false
                    ),
                    simulatorAllowedEnvironments: is_array($allowedEnvironments)
                        ? $allowedEnvironments
                        : [],
                    simulatorScenario: is_string($scenario)
                        ? $scenario
                        : null,
                );
            }
        );

        $this->app->bind(
            IntelbrasFacialCredentialSynchronizer::class,
            fn ($app): IntelbrasFacialCredentialSynchronizer => $app
                ->make(
                    IntelbrasFacialCredentialSynchronizerResolver::class
                )
                ->resolve()
        );
    }
}
