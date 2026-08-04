<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFirmwareVersion;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

final class IntelbrasFirmwareVersionTest extends TestCase
{
    public function test_it_normalizes_a_reported_firmware_version(): void
    {
        $firmware = new IntelbrasFirmwareVersion(
            ' version=2.000.00ib003.0.r,build:2021-06-22 '
        );

        $this->assertSame(
            'V2.000.00IB003.0.R.20210622',
            $firmware->value
        );
    }

    public function test_it_accepts_a_documented_date_version(): void
    {
        $firmware = new IntelbrasFirmwareVersion(
            '20260416'
        );

        $this->assertSame(
            '20260416',
            $firmware->value
        );
    }

    public function test_it_compares_date_versions_numerically(): void
    {
        $older = new IntelbrasFirmwareVersion(
            '20260416'
        );

        $newer = new IntelbrasFirmwareVersion(
            '20261002'
        );

        $this->assertGreaterThan(
            0,
            $newer->compareTo($older)
        );

        $this->assertTrue(
            $newer->isAtLeast($older)
        );
    }

    public function test_it_compares_structured_numeric_segments(): void
    {
        $lower = new IntelbrasFirmwareVersion(
            'V3.002.00IB000.0.R.20260716'
        );

        $higher = new IntelbrasFirmwareVersion(
            'V3.010.00IB000.0.R.20260716'
        );

        $this->assertGreaterThan(
            0,
            $higher->compareTo($lower)
        );
    }

    public function test_it_rejects_a_malformed_firmware(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new IntelbrasFirmwareVersion(
            '2026-04-16'
        );
    }

    public function test_it_rejects_comparison_between_incompatible_formats(): void
    {
        $dateVersion = new IntelbrasFirmwareVersion(
            '20260416'
        );

        $structuredVersion = new IntelbrasFirmwareVersion(
            'V3.002.00IB000.0.R.20260716'
        );

        $this->expectException(
            LogicException::class
        );

        $dateVersion->compareTo(
            $structuredVersion
        );
    }
}
