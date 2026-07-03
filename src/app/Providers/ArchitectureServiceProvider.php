<?php

namespace App\Providers;

use App\Core\Events\LaravelDomainEventDispatcher;
use App\Infrastructure\Persistence\Database\LaravelTransactionManager;
use App\Modules\Identity\Domain\Organizations\Repositories\OrganizationRepository;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EloquentOrganizationRepository;
use App\Support\Contracts\DomainEventDispatcher;
use App\Support\Contracts\TransactionManager;
use Illuminate\Support\ServiceProvider;

class ArchitectureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DomainEventDispatcher::class, LaravelDomainEventDispatcher::class);
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);

        $this->app->bind(OrganizationRepository::class, EloquentOrganizationRepository::class);
    }
}
