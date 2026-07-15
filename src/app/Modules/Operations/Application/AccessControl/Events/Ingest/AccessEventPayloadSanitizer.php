<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Ingest;

final class AccessEventPayloadSanitizer
{
    private const MAX_STRING_LENGTH = 255;

    /**
     * Somente metadados técnicos não biométricos.
     *
     * @var array<int, string>
     */
    private const ALLOWED_KEYS = [
        'source',
        'synthetic',
        'sequence',
        'event_kind',
        'result',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, bool|float|int|string>
     */
    public function sanitize(
        array $payload
    ): array {
        $sanitized = [];

        foreach (self::ALLOWED_KEYS as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            if (is_string($value)) {
                $cleanValue = preg_replace(
                    '/[\x00-\x1F\x7F]/u',
                    '',
                    $value
                );

                if ($cleanValue === null) {
                    continue;
                }

                $cleanValue = trim($cleanValue);

                if ($cleanValue === '') {
                    continue;
                }

                $sanitized[$key] = mb_substr(
                    $cleanValue,
                    0,
                    self::MAX_STRING_LENGTH
                );

                continue;
            }

            if (
                is_bool($value)
                || is_int($value)
                || is_float($value)
            ) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
