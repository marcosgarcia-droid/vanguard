<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use InvalidArgumentException;

final readonly class IntelbrasDeviceModel
{
    public const MAX_BYTES = 120;

    public string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if (
            $trimmed === ''
            || strlen($trimmed) > self::MAX_BYTES
            || preg_match('/^[\x20-\x7E]+$/D', $trimmed) !== 1
        ) {
            throw new InvalidArgumentException(
                'O modelo do equipamento Intelbras é inválido.'
            );
        }

        $normalized = preg_replace(
            '/[\s_-]+/',
            ' ',
            strtoupper($trimmed)
        );

        if (
            ! is_string($normalized)
            || preg_match(
                '/^[A-Z0-9]+(?: [A-Z0-9]+)*$/D',
                $normalized
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'O modelo do equipamento Intelbras é inválido.'
            );
        }

        $this->value = $normalized;
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
