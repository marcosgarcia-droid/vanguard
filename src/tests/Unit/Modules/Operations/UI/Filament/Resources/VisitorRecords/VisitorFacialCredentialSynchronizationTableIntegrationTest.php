<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use Tests\TestCase;

final class VisitorFacialCredentialSynchronizationTableIntegrationTest extends TestCase
{
    public function test_it_integrates_the_manual_action_in_the_expected_order(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Modules/Operations/UI/Filament/'
                .'Resources/VisitorRecords/Tables/'
                .'VisitorRecordsTable.php'
            )
        );

        self::assertIsString($source);

        $class =
            'CreateVisitorFacialCredentialSynchronizationAction';

        self::assertSame(
            2,
            substr_count(
                $source,
                $class
            )
        );

        $reprocessPosition = strpos(
            $source,
            'ReprocessVisitorFacialPhotoDerivativeAction::make()'
        );

        $createPosition = strpos(
            $source,
            'CreateVisitorFacialCredentialSynchronizationAction::make()'
        );

        $updatePosition = strpos(
            $source,
            'UpdateVisitorFacialPhotoAction::make()'
        );

        self::assertIsInt(
            $reprocessPosition
        );

        self::assertIsInt(
            $createPosition
        );

        self::assertIsInt(
            $updatePosition
        );

        self::assertLessThan(
            $createPosition,
            $reprocessPosition
        );

        self::assertLessThan(
            $updatePosition,
            $createPosition
        );
    }

    public function test_the_integrated_action_remains_creation_only_and_permission_protected(): void
    {
        $action = file_get_contents(
            base_path(
                'app/Modules/Operations/UI/Filament/'
                .'Resources/VisitorRecords/Actions/'
                .'CreateVisitorFacialCredentialSynchronizationAction.php'
            )
        );

        self::assertIsString($action);

        self::assertStringContainsString(
            "'createFacialCredentialSynchronization'",
            $action
        );

        self::assertStringContainsString(
            'Gate::authorize(',
            $action
        );

        self::assertStringContainsString(
            'CreateFacialCredentialSynchronizationUseCase::class',
            $action
        );

        foreach (
            [
                'ExecuteFacialCredentialSynchronizationUseCase',
                'Http::',
                'Queue::',
                'Bus::',
                'dispatch(',
                'dispatchSync(',
                'curl_',
            ] as $prohibited
        ) {
            self::assertStringNotContainsString(
                $prohibited,
                $action
            );
        }
    }
}
