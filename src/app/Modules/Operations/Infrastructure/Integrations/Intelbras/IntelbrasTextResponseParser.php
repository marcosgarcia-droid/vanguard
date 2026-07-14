<?php

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras;

final class IntelbrasTextResponseParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $body): array
    {
        $values = [];

        $lines = preg_split(
            '/\r\n|\r|\n/',
            trim($body)
        ) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);

            if ($key === '') {
                continue;
            }

            $values[$key] = $this->parseValue(
                trim($value)
            );
        }

        return $values;
    }

    private function parseValue(string $value): mixed
    {
        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            default => $this->parseNumericValue($value),
        };
    }

    private function parseNumericValue(
        string $value
    ): mixed {
        if (preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        if (
            preg_match(
                '/^-?\d+\.\d+$/',
                $value
            ) === 1
        ) {
            return (float) $value;
        }

        return $value;
    }
}
