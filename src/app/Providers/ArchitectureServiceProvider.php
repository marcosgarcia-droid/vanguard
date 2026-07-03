<?php

namespace App\Providers;

use App\Core\Events\LaravelDomainEventDispatcher;
use App\Support\Contracts\DomainEventDispatcher;
use Illuminate\Support\ServiceProvider;

class ArchitectureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            DomainEventDispatcher::class,
            LaravelDomainEventDispatcher::class,
        );
    }

    public function boot(): void
    {
        //
    }
}
