<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\IntelbrasTextResponseParser;
use PHPUnit\Framework\TestCase;

class IntelbrasTextResponseParserTest extends TestCase
{
    public function test_it_parses_intelbras_plain_text_responses(): void
    {
        $body = <<<'TXT'
result=2026-07-14 15:20:30
table.AccessControl[0].BreakInAlarmEnable=true
table.AccessControl[0].CloseTimeout=10
table.AccessControl[0].UnlockHoldInterval=3000
Info.status=Close
version=2.000.00IB003.0.R,build:2021-06-22
TXT;

        $parsed = (new IntelbrasTextResponseParser)
            ->parse($body);

        $this->assertSame(
            '2026-07-14 15:20:30',
            $parsed['result']
        );

        $this->assertTrue(
            $parsed[
                'table.AccessControl[0].BreakInAlarmEnable'
            ]
        );

        $this->assertSame(
            10,
            $parsed[
                'table.AccessControl[0].CloseTimeout'
            ]
        );

        $this->assertSame(
            3000,
            $parsed[
                'table.AccessControl[0].UnlockHoldInterval'
            ]
        );

        $this->assertSame(
            'Close',
            $parsed['Info.status']
        );
    }
}
