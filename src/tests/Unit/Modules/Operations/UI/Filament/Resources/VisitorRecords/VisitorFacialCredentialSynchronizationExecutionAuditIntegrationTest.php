<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use Tests\TestCase;

final class VisitorFacialCredentialSynchronizationExecutionAuditIntegrationTest extends TestCase
{
    public function test_the_action_records_results_failures_and_refreshes_relations(): void
    {
        $source = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/VisitorRecords/Actions/ExecuteVisitorFacialCredentialSynchronizationAction.php'
            )
        );

        self::assertIsString(
            $source
        );

        foreach ([
            'VisitorFacialCredentialSynchronizationExecutionAudit::record(',
            'VisitorFacialCredentialSynchronizationExecutionAudit::failure(',
            'private static function refresh(',
            "'facialCredentialSynchronizations.accessDevice'",
            "'facialCredentialSynchronizations.latestAttempt'",
            'report($throwable);',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_the_audit_uses_the_visitor_history_without_sensitive_fields(): void
    {
        $source = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/VisitorRecords/Actions/VisitorFacialCredentialSynchronizationExecutionAudit.php'
            )
        );

        self::assertIsString(
            $source
        );

        foreach ([
            "activity('visitor_management')",
            '->causedBy($user)',
            '->performedOn($visitor)',
            '->event(',
            '->withProperties(',
            '->log(',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $source
            );
        }

        foreach ([
            'plan_fingerprint',
            'context_fingerprint',
            'failure_code',
            'source_sha256',
            'photo_path',
            'credential_username',
            'credential_password',
            'raw_payload',
            'base64',
            'Http::',
            'Guzzle',
            'curl',
            'Digest',
            'dispatch(',
            'dispatchSync(',
            'Storage::',
            'readStream',
        ] as $prohibited) {
            self::assertStringNotContainsString(
                $prohibited,
                $source
            );
        }
    }
}
