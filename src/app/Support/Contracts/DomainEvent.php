<?php

namespace App\Support\Contracts;

use DateTimeImmutable;

interface DomainEvent
{
    public function name(): string;

    public function occurredAt(): DateTimeImmutable;

    public function payload(): array;
}
