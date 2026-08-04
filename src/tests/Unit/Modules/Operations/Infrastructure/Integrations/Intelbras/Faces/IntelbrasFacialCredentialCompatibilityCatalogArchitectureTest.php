<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use Tests\TestCase;

final class IntelbrasFacialCredentialCompatibilityCatalogArchitectureTest extends TestCase
{
    public function test_catalog_foundation_has_no_transport_or_framework_dependencies(): void
    {
        $directory = app_path(
            'Modules/Operations/Infrastructure/Integrations/Intelbras/Faces'
        );

        $files = [
            'IntelbrasDeviceModel.php',
            'IntelbrasFirmwareVersion.php',
            'IntelbrasFacialCredentialCompatibilityResolutionStatus.php',
            'IntelbrasFacialCredentialCompatibilityResolution.php',
            'IntelbrasFacialCredentialCompatibilityCatalog.php',
            'ExplicitIntelbrasFacialCredentialCompatibilityCatalog.php',
            'DocumentedIntelbrasFacialCredentialCompatibilityCatalog.php',
        ];

        $forbiddenFragments = [
            'Illuminate\\',
            'Laravel\\',
            'Guzzle',
            'Http::',
            'DB::',
            'Queue::',
            'dispatch(',
            'base64_',
            'PDO',
            'Model extends',
        ];

        foreach ($files as $file) {
            $path = $directory.'/'.$file;

            $this->assertFileExists($path);

            $contents = file_get_contents($path);

            $this->assertIsString($contents);

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString(
                    $fragment,
                    $contents,
                    sprintf(
                        'Dependência proibida encontrada em %s.',
                        $file
                    )
                );
            }
        }
    }
}
