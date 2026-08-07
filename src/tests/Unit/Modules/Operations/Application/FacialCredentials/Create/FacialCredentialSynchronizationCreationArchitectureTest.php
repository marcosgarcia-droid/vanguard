<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialCredentials\Create;

use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationRepository;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationUseCase;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\ConfiguredIntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentCreateFacialCredentialSynchronizationRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationAttemptRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use Tests\TestCase;

final class FacialCredentialSynchronizationCreationArchitectureTest extends TestCase
{
    public function test_creation_layer_has_no_transport_or_binary_access(): void
    {
        $directory = app_path(
            'Modules/Operations/Application/FacialCredentials/Create'
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
                'Illuminate\\Http',
                'GuzzleHttp',
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

    public function test_container_resolves_safe_creation_dependencies(): void
    {
        self::assertInstanceOf(
            ConfiguredIntelbrasFacialCredentialCompatibilityCatalog::class,
            app(
                IntelbrasFacialCredentialCompatibilityCatalog::class
            )
        );

        self::assertInstanceOf(
            EloquentCreateFacialCredentialSynchronizationRepository::class,
            app(
                CreateFacialCredentialSynchronizationRepository::class
            )
        );

        self::assertInstanceOf(
            CreateFacialCredentialSynchronizationUseCase::class,
            app(
                CreateFacialCredentialSynchronizationUseCase::class
            )
        );
    }

    public function test_fingerprints_are_hidden_from_model_serialization(): void
    {
        $synchronization =
            new FacialCredentialSynchronizationRecord;

        $attempt =
            new FacialCredentialSynchronizationAttemptRecord;

        self::assertContains(
            'plan_fingerprint',
            $synchronization->getHidden()
        );

        self::assertContains(
            'context_fingerprint',
            $synchronization->getHidden()
        );

        self::assertContains(
            'plan_fingerprint',
            $attempt->getHidden()
        );
    }

    public function test_migration_does_not_import_application_enums(): void
    {
        $source = file_get_contents(
            database_path(
                'migrations/2026_08_05_103500_create_facial_credential_synchronization_tables.php'
            )
        );

        self::assertIsString($source);

        self::assertStringNotContainsString(
            'use App\\',
            $source
        );

        self::assertSame(
            2,
            substr_count(
                $source,
                "->default('pending');"
            )
        );
    }
}
