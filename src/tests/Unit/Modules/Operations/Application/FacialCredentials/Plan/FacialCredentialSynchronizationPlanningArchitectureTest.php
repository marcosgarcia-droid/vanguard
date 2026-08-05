<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialCredentials\Plan;

use Tests\TestCase;

final class FacialCredentialSynchronizationPlanningArchitectureTest extends TestCase
{
    public function test_planning_layer_has_no_transport_or_persistence(): void
    {
        $directory = app_path(
            'Modules/Operations/Application/FacialCredentials/Plan'
        );

        $files = glob($directory.'/*.php');

        self::assertIsArray($files);
        self::assertNotEmpty($files);

        $source = '';

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            self::assertIsString($contents);

            $source .= "\n".$contents;
        }

        $forbiddenFragments = [
            'Illuminate\\Database',
            'Illuminate\\Http',
            'GuzzleHttp',
            'Eloquent',
            'DB::',
            'Http::',
            'Storage::',
            'curl_',
            'file_get_contents(\'http',
            '->synchronize(',
        ];

        foreach ($forbiddenFragments as $fragment) {
            self::assertStringNotContainsString(
                $fragment,
                $source
            );
        }
    }
}
