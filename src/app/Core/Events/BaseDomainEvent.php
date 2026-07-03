<?php

namespace App\Core\Events;

use App\Support\Contracts\DomainEvent;
use DateTimeImmutable;

abstract class BaseDomainEvent implements DomainEvent
{
    private readonly DateTimeImmutable $occurredAt;

    public function __construct(?DateTimeImmutable $occurredAt = null)
    {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable;
    }

    public function name(): string
    {
        return static::class;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [];
    }
}
