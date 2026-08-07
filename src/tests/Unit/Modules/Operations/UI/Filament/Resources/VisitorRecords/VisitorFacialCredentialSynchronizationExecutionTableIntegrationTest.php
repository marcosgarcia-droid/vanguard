<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use Tests\TestCase;

final class VisitorFacialCredentialSynchronizationExecutionTableIntegrationTest extends TestCase
{
    public function test_the_execution_action_is_registered_once_after_the_creation_action(): void
    {
        $source = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/VisitorRecords/Tables/VisitorRecordsTable.php'
            )
        );

        self::assertIsString(
            $source
        );

        $createImport =
            'use App\\Modules\\Operations\\UI\\Filament\\Resources'
            .'\\VisitorRecords\\Actions'
            .'\\CreateVisitorFacialCredentialSynchronizationAction;';

        $executeImport =
            'use App\\Modules\\Operations\\UI\\Filament\\Resources'
            .'\\VisitorRecords\\Actions'
            .'\\ExecuteVisitorFacialCredentialSynchronizationAction;';

        $createAction =
            'CreateVisitorFacialCredentialSynchronizationAction::make(),';

        $executeAction =
            'ExecuteVisitorFacialCredentialSynchronizationAction::make(),';

        self::assertSame(
            1,
            substr_count(
                $source,
                $executeImport
            )
        );

        self::assertSame(
            1,
            substr_count(
                $source,
                $executeAction
            )
        );

        self::assertLessThan(
            strpos(
                $source,
                $executeImport
            ),
            strpos(
                $source,
                $createImport
            )
        );

        self::assertLessThan(
            strpos(
                $source,
                $executeAction
            ),
            strpos(
                $source,
                $createAction
            )
        );
    }

    public function test_the_action_contains_no_queue_or_physical_transport(): void
    {
        $source = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/VisitorRecords/Actions/ExecuteVisitorFacialCredentialSynchronizationAction.php'
            )
        );

        self::assertIsString(
            $source
        );

        self::assertStringContainsString(
            'ExecuteFacialCredentialSynchronizationUseCase',
            $source
        );

        self::assertStringNotContainsString(
            'CreateFacialCredentialSynchronizationUseCase',
            $source
        );

        foreach ([
            'Http::',
            'Guzzle',
            'curl',
            'Digest',
            'dispatch(',
            'dispatchSync(',
            'Storage::',
            'readStream',
            'base64',
        ] as $prohibited) {
            self::assertStringNotContainsString(
                $prohibited,
                $source
            );
        }
    }
}
