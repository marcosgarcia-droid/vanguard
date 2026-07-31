<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

final readonly class IntelbrasAccessUserPayloadNormalizer
{
    private const ALLOWED_KEYS = [
        'external_user_id',
        'display_name',
        'user_type',
        'authority',
        'door_numbers',
        'time_section_numbers',
        'valid_from',
        'valid_to',
    ];

    /**
     * Campos desconhecidos são descartados pela allowlist.
     *
     * Senhas, fotos, templates, embeddings, payloads crus e
     * demais dados não cadastrados nunca alcançam o DTO.
     *
     * @param  array<string, mixed>  $input
     */
    public function normalize(
        array $input
    ): IntelbrasAccessUserPayload {
        $allowedInput = array_intersect_key(
            $input,
            array_flip(self::ALLOWED_KEYS)
        );

        return new IntelbrasAccessUserPayload(
            externalUserId: $this->requiredString(
                $allowedInput,
                'external_user_id'
            ),
            displayName: $this->requiredString(
                $allowedInput,
                'display_name'
            ),
            userType: $this->requiredInteger(
                $allowedInput,
                'user_type'
            ),
            authority: $this->requiredInteger(
                $allowedInput,
                'authority'
            ),
            doorNumbers: $this->requiredIntegerList(
                $allowedInput,
                'door_numbers'
            ),
            timeSectionNumbers: $this->requiredIntegerList(
                $allowedInput,
                'time_section_numbers'
            ),
            validFrom: $this->requiredDateTime(
                $allowedInput,
                'valid_from'
            ),
            validTo: $this->requiredDateTime(
                $allowedInput,
                'valid_to'
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function requiredString(
        array $input,
        string $key,
    ): string {
        $value = $this->requiredValue(
            $input,
            $key
        );

        if (! is_string($value)) {
            throw new InvalidArgumentException(
                "O campo {$key} deve ser textual."
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function requiredInteger(
        array $input,
        string $key,
    ): int {
        return $this->normalizeInteger(
            $this->requiredValue(
                $input,
                $key
            ),
            $key
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<int>
     */
    private function requiredIntegerList(
        array $input,
        string $key,
    ): array {
        $value = $this->requiredValue(
            $input,
            $key
        );

        if (
            ! is_array($value)
            || ! array_is_list($value)
        ) {
            throw new InvalidArgumentException(
                "O campo {$key} deve ser uma lista."
            );
        }

        $normalized = [];

        foreach ($value as $item) {
            $normalized[] = $this->normalizeInteger(
                $item,
                $key
            );
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function requiredDateTime(
        array $input,
        string $key,
    ): DateTimeImmutable {
        $value = $this->requiredValue(
            $input,
            $key
        );

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface(
                $value
            );
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(
                "O campo {$key} deve conter uma data válida."
            );
        }

        $normalized = trim($value);

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $normalized,
            new DateTimeZone('UTC')
        );

        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                $errors !== false
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
            || $date->format('Y-m-d H:i:s') !== $normalized
        ) {
            throw new InvalidArgumentException(
                "O campo {$key} deve usar o formato Y-m-d H:i:s."
            );
        }

        return $date;
    }

    private function normalizeInteger(
        mixed $value,
        string $key,
    ): int {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            if (
                preg_match(
                    '/^-?\d+$/D',
                    $normalized
                ) === 1
            ) {
                $filtered = filter_var(
                    $normalized,
                    FILTER_VALIDATE_INT
                );

                if ($filtered !== false) {
                    return $filtered;
                }
            }
        }

        throw new InvalidArgumentException(
            "O campo {$key} deve conter somente números inteiros."
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function requiredValue(
        array $input,
        string $key,
    ): mixed {
        if (! array_key_exists($key, $input)) {
            throw new InvalidArgumentException(
                "O campo obrigatório {$key} não foi informado."
            );
        }

        return $input[$key];
    }
}
