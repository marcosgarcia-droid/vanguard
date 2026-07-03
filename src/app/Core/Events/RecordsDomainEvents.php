<?php

namespace App\Core\Events;

use App\Support\Contracts\DomainEvent;

trait RecordsDomainEvents
{
    /**
     * @var list<DomainEvent>
     */
    private array $recordedDomainEvents = [];

    protected function recordDomainEvent(DomainEvent $event): void
    {
        $this->recordedDomainEvents[] = $event;
    }

    /**
     * @return list<DomainEvent>
     */
    public function releaseDomainEvents(): array
    {
        $events = $this->recordedDomainEvents;

        $this->recordedDomainEvents = [];

        return $events;
    }

    public function clearDomainEvents(): void
    {
        $this->recordedDomainEvents = [];
    }
}
