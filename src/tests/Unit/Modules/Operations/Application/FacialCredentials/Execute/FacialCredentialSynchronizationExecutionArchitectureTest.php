<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialCredentials\Execute;

use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationRepository;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationUseCase;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentExecuteFacialCredentialSynchronizationRepository;
use Tests\TestCase;

final class FacialCredentialSynchronizationExecutionArchitectureTest extends TestCase
{
    public function test_application_layer_has_no_transport_or_persistence(): void
    {
        $directory = app_path(
            'Modules/Operations/Application/FacialCredentials/Execute'
        );

        $files = glob(
            $directory.'/*.php'
        );

        self::assertIsArray($files);
        self::assertNotEmpty($files);

        $source = '';

        foreach ($files as $file) {
            $contents = file_get_contents(
                $file
            );

            self::assertIsString($contents);

            $source .= "\n".$contents;
        }

        foreach (
            [
                'Illuminate\\Database',
                'Illuminate\\Http',
                'GuzzleHttp',
                'DB::',
                'Http::',
                'Storage::',
                'curl_',
                'base64',
                'credential_password',
                'raw_payload',
                'sanitized_response',
                '->synchronize(',
            ] as $fragment
        ) {
            self::assertStringNotContainsString(
                $fragment,
                $source
            );
        }
    }

    public function test_container_resolves_execution_dependencies(): void
    {
        self::assertInstanceOf(
            EloquentExecuteFacialCredentialSynchronizationRepository::class,
            app(
                ExecuteFacialCredentialSynchronizationRepository::class
            )
        );

        self::assertInstanceOf(
            ExecuteFacialCredentialSynchronizationUseCase::class,
            app(
                ExecuteFacialCredentialSynchronizationUseCase::class
            )
        );
    }

    public function test_repository_explicitly_limits_synchronizer_classes(): void
    {
        $source = file_get_contents(
            app_path(
                'Modules/Operations/Infrastructure/Persistence/Eloquent/EloquentExecuteFacialCredentialSynchronizationRepository.php'
            )
        );

        self::assertIsString($source);

        self::assertStringContainsString(
            'DisabledIntelbrasFacialCredentialSynchronizer',
            $source
        );

        self::assertStringContainsString(
            'SimulatedIntelbrasFacialCredentialSynchronizer',
            $source
        );

        self::assertStringContainsString(
            "'provider_not_allowed'",
            $source
        );

        self::assertStringContainsString(
            'DB::transaction',
            $source
        );

        self::assertStringContainsString(
            'lockForUpdate',
            $source
        );

        foreach (
            [
                'Illuminate\\Http',
                'GuzzleHttp',
                'Http::',
                'Storage::',
                'curl_',
                'base64',
                'credential_username',
                'credential_password',
                'raw_payload',
                'sanitized_response',
            ] as $fragment
        ) {
            self::assertStringNotContainsString(
                $fragment,
                $source
            );
        }
    }
}
