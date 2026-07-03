<?php

namespace App\Core\Events;

use App\Support\Contracts\DomainEvent;
use App\Support\Contracts\DomainEventDispatcher;
use Illuminate\Contracts\Events\Dispatcher;

readonly class LaravelDomainEventDispatcher implements DomainEventDispatcher
{
    public function __construct(
        private Dispatcher $dispatcher,
    ) {}

    public function dispatch(DomainEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }

    public function dispatchMany(iterable $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }
}
