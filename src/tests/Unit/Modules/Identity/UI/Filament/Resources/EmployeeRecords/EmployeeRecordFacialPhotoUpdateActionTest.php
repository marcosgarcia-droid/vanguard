<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\EmployeeRecords;

use Tests\TestCase;

final class EmployeeRecordFacialPhotoUpdateActionTest extends TestCase
{
    public function test_employee_table_registers_the_facial_photo_update_action(): void
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
            'UpdateEmployeeFacialPhotoAction::make()',
            $table
        );
    }

    public function test_action_uses_the_employee_biometric_authorization_boundary(): void
    {
        $action = $this->actionSource();

        foreach (
            [
                "Action::make('updateFacialPhoto')",
                "->label('Atualizar foto facial')",
                "'manageFacialPhoto'",
                'Gate::authorize(',
                'EmployeeFacialPhotoCaptureRegistrar::confirmationContext(',
                "FacialPhotoCapture::make('photo_capture')",
                'ConfirmFacialPhotoPreviewUseCase::class',
                'EmployeeFacialPhotoCaptureRegistrar::class',
                'RegisterFacialPhotoException',
                '->databaseTransaction()',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $action
            );
        }

        foreach (
            [
                "'update',",
                'VisitorRecord',
                'VisitorFacialPhotoCaptureRegistrar',
                'RegisterVisitorFacialPhotoException',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $action
            );
        }
    }

    public function test_action_keeps_profile_photo_separate_from_biometric_photo(): void
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

        $this->assertStringContainsString(
            "'facialPhotos'",
            $action
        );
    }

    public function test_action_maps_camera_and_file_upload_sources_explicitly(): void
    {
        $action = $this->actionSource();

        foreach (
            [
                "'foto-facial-camera-'",
                'FacialPhotoSource::Webcam',
                'FacialPhotoSource::FileUpload',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $action
            );
        }
    }

    private function actionSource(): string
    {
        $action = file_get_contents(
            app_path(
                'Modules/Identity/UI/Filament/Resources/'
                .'EmployeeRecords/Actions/UpdateEmployeeFacialPhotoAction.php'
            )
        );

        $this->assertIsString(
            $action
        );

        return $action;
    }
}
