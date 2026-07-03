<?php

namespace Tests\Unit\Core\Events;

use App\Core\Events\BaseDomainEvent;
use App\Core\Events\LaravelDomainEventDispatcher;
use App\Support\Contracts\DomainEventDispatcher;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LaravelDomainEventDispatcherTest extends TestCase
{
    public function test_it_resolves_the_domain_event_dispatcher_contract(): void
    {
        $dispatcher = $this->app->make(DomainEventDispatcher::class);

        $this->assertInstanceOf(LaravelDomainEventDispatcher::class, $dispatcher);
    }

    public function test_it_dispatches_a_domain_event(): void
    {
        Event::fake();

        $event = new class extends BaseDomainEvent {};

        app(DomainEventDispatcher::class)->dispatch($event);

        Event::assertDispatched($event::class);
    }

    public function test_it_dispatches_many_domain_events(): void
    {
        Event::fake();

        $firstEvent = new class extends BaseDomainEvent {};
        $secondEvent = new class extends BaseDomainEvent {};

        app(DomainEventDispatcher::class)->dispatchMany([
            $firstEvent,
            $secondEvent,
        ]);

        Event::assertDispatched($firstEvent::class);
        Event::assertDispatched($secondEvent::class);
    }
}
