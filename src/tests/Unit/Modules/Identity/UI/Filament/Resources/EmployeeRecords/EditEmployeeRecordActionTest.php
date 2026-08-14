<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\EmployeeRecords;

use Tests\TestCase;

final class EditEmployeeRecordActionTest extends TestCase
{
    public function test_employee_table_uses_the_specialized_edit_action(): void
    {
        $table = file_get_contents(
            app_path(
                'Modules/Identity/UI/Filament/Resources/'
                .'EmployeeRecords/Tables/EmployeeRecordsTable.php'
            )
        );

        $this->assertIsString(
            $table
        );

        $this->assertStringContainsString(
            'EditEmployeeRecordAction::make()',
            $table
        );

        $this->assertStringNotContainsString(
            'EditAction::make()',
            $table
        );
    }

    public function test_edit_action_confirms_and_registers_optional_biometric_photo(): void
    {
        $action = $this->actionSource();

        foreach (
            [
                '->databaseTransaction()',
                '->mutateDataUsing(',
                '->after(',
                "'photo_capture'",
                "'photo_capture_receipt'",
                "'manageFacialPhoto'",
                'ConfirmFacialPhotoPreviewUseCase::class',
                'EmployeeFacialPhotoCaptureRegistrar::class',
                'FacialPhotoSource::Webcam',
                'FacialPhotoSource::FileUpload',
                "'foto-facial-camera-'",
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $action
            );
        }
    }

    public function test_edit_action_does_not_treat_profile_photo_as_biometric_photo(): void
    {
        $action = $this->actionSource();

        foreach (
            [
                'photo_disk',
                'photo_path',
                'photo_uploaded_at',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $action
            );
        }
    }

    private function actionSource(): string
    {
        $action = file_get_contents(
            app_path(
                'Modules/Identity/UI/Filament/Resources/'
                .'EmployeeRecords/Actions/EditEmployeeRecordAction.php'
            )
        );

        $this->assertIsString(
            $action
        );

        return $action;
    }
}
