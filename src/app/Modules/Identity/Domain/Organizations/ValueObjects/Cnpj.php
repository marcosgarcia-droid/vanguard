<?php

namespace App\Modules\Identity\Domain\Organizations\ValueObjects;

use InvalidArgumentException;
use Stringable;

final readonly class Cnpj implements Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = preg_replace('/\D/', '', $value) ?? '';

        if (! self::isValid($normalized)) {
            throw new InvalidArgumentException('Invalid CNPJ.');
        }

        $this->value = $normalized;
    }

    public static function fromNullable(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function formatted(): string
    {
        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($this->value, 0, 2),
            substr($this->value, 2, 3),
            substr($this->value, 5, 3),
            substr($this->value, 8, 4),
            substr($this->value, 12, 2),
        );
    }

    public function root(): string
    {
        return substr($this->value, 0, 8);
    }

    public function branch(): string
    {
        return substr($this->value, 8, 4);
    }

    public function checkDigits(): string
    {
        return substr($this->value, 12, 2);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function isValid(string $value): bool
    {
        if (strlen($value) !== 14) {
            return false;
        }

        if (preg_match('/^(\d)\1{13}$/', $value) === 1) {
            return false;
        }

        return self::calculateDigit($value, 12) === (int) $value[12]
            && self::calculateDigit($value, 13) === (int) $value[13];
    }

    private static function calculateDigit(string $value, int $position): int
    {
        $weights = $position === 12
            ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
            : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $sum = 0;

        for ($index = 0; $index < $position; $index++) {
            $sum += (int) $value[$index] * $weights[$index];
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
