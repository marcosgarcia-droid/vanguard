<?php

namespace App\Modules\Identity\Domain\Organizations\ValueObjects;

use InvalidArgumentException;
use Stringable;

final readonly class OrganizationId implements Stringable
{
    public function __construct(
        private string $value,
    ) {
        if ($this->value === '') {
            throw new InvalidArgumentException('Organization ID cannot be empty.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
