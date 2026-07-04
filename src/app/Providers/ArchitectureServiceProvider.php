<?php

namespace App\Providers;

use App\Core\Events\LaravelDomainEventDispatcher;
use App\Infrastructure\Persistence\Database\LaravelTransactionManager;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSyncRepository;
use App\Modules\Identity\Domain\Organizations\Repositories\OrganizationRepository;
use App\Modules\Identity\Infrastructure\Integrations\CnpjLookup\BrasilApiCnpjLookupProvider;
use App\Modules\Identity\Infrastructure\Integrations\CnpjLookup\FailoverCnpjLookupProvider;
use App\Modules\Identity\Infrastructure\Integrations\CnpjLookup\ReceitaWsCnpjLookupProvider;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EloquentCnpjLookupSyncRepository;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EloquentOrganizationRepository;
use App\Support\Contracts\DomainEventDispatcher;
use App\Support\Contracts\TransactionManager;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class ArchitectureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DomainEventDispatcher::class, LaravelDomainEventDispatcher::class);
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);

        $this->app->bind(OrganizationRepository::class, EloquentOrganizationRepository::class);
        $this->app->bind(CnpjLookupSyncRepository::class, EloquentCnpjLookupSyncRepository::class);

        $this->app->bind(
            CnpjLookupProvider::class,
            fn (): CnpjLookupProvider => new FailoverCnpjLookupProvider($this->configuredCnpjLookupProviders()),
        );
    }

    /**
     * @return list<CnpjLookupProvider>
     */
    private function configuredCnpjLookupProviders(): array
    {
        $providerNames = config('vanguard.integrations.cnpj_lookup.providers', ['brasilapi', 'receitaws']);

        if (! is_array($providerNames)) {
            throw new InvalidArgumentException('CNPJ lookup providers configuration must be an array.');
        }

        return array_map(
            fn (mixed $providerName): CnpjLookupProvider => $this->makeCnpjLookupProvider((string) $providerName),
            $providerNames,
        );
    }

    private function makeCnpjLookupProvider(string $providerName): CnpjLookupProvider
    {
        return match ($providerName) {
            'brasilapi' => new BrasilApiCnpjLookupProvider(
                baseUrl: (string) config('vanguard.integrations.cnpj_lookup.brasilapi.base_url', 'https://brasilapi.com.br'),
            ),
            'receitaws' => new ReceitaWsCnpjLookupProvider(
                baseUrl: (string) config('vanguard.integrations.cnpj_lookup.receitaws.base_url', 'https://www.receitaws.com.br'),
            ),
            default => throw new InvalidArgumentException(sprintf(
                'Unsupported CNPJ lookup provider [%s].',
                $providerName,
            )),
        };
    }
}
