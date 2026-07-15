<?php

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras;

final class IntelbrasConfigurationResponseSanitizer
{
    private const MAX_STRING_LENGTH_BYTES = 1024;

    /**
     * @param  array<string, mixed>  $parsedResponse
     * @return array<string, bool|float|int|string>
     */
    public function sanitize(
        IntelbrasReadOnlyEndpoint $endpoint,
        array $parsedResponse
    ): array {
        $sanitized = [];

        foreach (
            $endpoint->allowedResponseKeys() as $allowedKey
        ) {
            if (
                ! array_key_exists(
                    $allowedKey,
                    $parsedResponse
                )
            ) {
                continue;
            }

            $value = $this->sanitizeValue(
                $parsedResponse[$allowedKey]
            );

            if ($value === null) {
                continue;
            }

            $sanitized[$allowedKey] = $value;
        }

        return $sanitized;
    }

    private function sanitizeValue(
        mixed $value
    ): bool|float|int|string|null {
        if (
            is_bool($value)
            || is_int($value)
            || is_float($value)
        ) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
            '',
            $value
        );

        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if (
            strlen($value)
            > self::MAX_STRING_LENGTH_BYTES
        ) {
            return null;
        }

        return $value;
    }
}
