<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use InvalidArgumentException;
use LogicException;

final readonly class IntelbrasFirmwareVersion
{
    public const MAX_BYTES = 120;

    private const DATE_PATTERN = '/^\d{8}$/D';

    private const STRUCTURED_PATTERN =
        '/^V\d+(?:\.\d+){2}IB\d+(?:\.\d+)*\.R(?:\.\d{8})?$/D';

    public string $value;

    /**
     * @var list<string>
     */
    private array $comparisonTokens;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if (
            $trimmed === ''
            || strlen($trimmed) > self::MAX_BYTES
            || preg_match('/^[\x20-\x7E]+$/D', $trimmed) !== 1
        ) {
            throw new InvalidArgumentException(
                'A versão de firmware Intelbras é inválida.'
            );
        }

        $normalized = strtoupper($trimmed);

        $normalized = $this->normalizeReportedVersion($normalized);

        if (
            preg_match(self::DATE_PATTERN, $normalized) !== 1
            && preg_match(self::STRUCTURED_PATTERN, $normalized) !== 1
        ) {
            throw new InvalidArgumentException(
                'A versão de firmware Intelbras é inválida.'
            );
        }

        $this->validateTrailingDate($normalized);

        $tokens = preg_split(
            '/(\d+)/',
            $normalized,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if (! is_array($tokens) || $tokens === []) {
            throw new InvalidArgumentException(
                'A versão de firmware Intelbras é inválida.'
            );
        }

        $this->value = $normalized;
        $this->comparisonTokens = array_values($tokens);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function compareTo(self $other): int
    {
        if (
            count($this->comparisonTokens)
            !== count($other->comparisonTokens)
        ) {
            throw new LogicException(
                'As versões de firmware possuem formatos incompatíveis.'
            );
        }

        foreach (
            $this->comparisonTokens as $index => $leftToken
        ) {
            $rightToken = $other->comparisonTokens[$index];

            $leftIsNumeric = ctype_digit($leftToken);
            $rightIsNumeric = ctype_digit($rightToken);

            if ($leftIsNumeric !== $rightIsNumeric) {
                throw new LogicException(
                    'As versões de firmware possuem formatos incompatíveis.'
                );
            }

            if (! $leftIsNumeric) {
                if ($leftToken !== $rightToken) {
                    throw new LogicException(
                        'As versões de firmware possuem formatos incompatíveis.'
                    );
                }

                continue;
            }

            $comparison = self::compareNumericTokens(
                $leftToken,
                $rightToken
            );

            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }

    public function isAtLeast(self $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function normalizeReportedVersion(string $value): string
    {
        $reportedPattern =
            '/^(?:VERSION=)?'
            .'(V?\d+(?:\.\d+){2}IB\d+(?:\.\d+)*\.R)'
            .',BUILD:(\d{4})-(\d{2})-(\d{2})$/D';

        if (preg_match($reportedPattern, $value, $matches) === 1) {
            $this->assertValidDate(
                year: $matches[2],
                month: $matches[3],
                day: $matches[4],
            );

            $baseVersion = $matches[1];

            if (! str_starts_with($baseVersion, 'V')) {
                $baseVersion = 'V'.$baseVersion;
            }

            return $baseVersion
                .'.'
                .$matches[2]
                .$matches[3]
                .$matches[4];
        }

        if (str_starts_with($value, 'VERSION=')) {
            $value = substr($value, strlen('VERSION='));
        }

        if (
            preg_match(self::DATE_PATTERN, $value) !== 1
            && ! str_starts_with($value, 'V')
        ) {
            $value = 'V'.$value;
        }

        return $value;
    }

    private function validateTrailingDate(string $value): void
    {
        if (
            preg_match(
                '/(?:^|\.)(\d{4})(\d{2})(\d{2})$/D',
                $value,
                $matches
            ) !== 1
        ) {
            return;
        }

        $this->assertValidDate(
            year: $matches[1],
            month: $matches[2],
            day: $matches[3],
        );
    }

    private function assertValidDate(
        string $year,
        string $month,
        string $day,
    ): void {
        if (
            ! checkdate(
                (int) $month,
                (int) $day,
                (int) $year
            )
        ) {
            throw new InvalidArgumentException(
                'A data contida no firmware Intelbras é inválida.'
            );
        }
    }

    private static function compareNumericTokens(
        string $left,
        string $right,
    ): int {
        $normalizedLeft = ltrim($left, '0');
        $normalizedRight = ltrim($right, '0');

        if ($normalizedLeft === '') {
            $normalizedLeft = '0';
        }

        if ($normalizedRight === '') {
            $normalizedRight = '0';
        }

        $lengthComparison =
            strlen($normalizedLeft) <=> strlen($normalizedRight);

        if ($lengthComparison !== 0) {
            return $lengthComparison;
        }

        return strcmp($normalizedLeft, $normalizedRight);
    }
}
