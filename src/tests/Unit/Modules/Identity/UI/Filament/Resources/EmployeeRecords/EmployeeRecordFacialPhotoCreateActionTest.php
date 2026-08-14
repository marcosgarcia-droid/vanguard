<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\EmployeeRecords;

use App\Modules\Operations\Infrastructure\Storage\EmployeeFacialPhotoCaptureRegistrar;
use Tests\TestCase;

final class EmployeeRecordFacialPhotoCreateActionTest extends TestCase
{
    public function test_employee_main_tab_exposes_facial_capture_for_create_and_update(): void
    {
        $form = file_get_contents(
            app_path(
                'Modules/Identity/UI/Filament/Resources/'
                .'EmployeeRecords/Schemas/EmployeeRecordForm.php'
            )
        );

        $this->assertIsString($form);

        foreach (
            [
                "Tab::make('Funcionário')",
                'FacialPhotoCapture::make(',
                "'photo_capture'",
                "'photo_capture_request_id'",
                "'photo_capture_receipt'",
                'EmployeeFacialPhotoCaptureRegistrar::creationConfirmationContext()',
                'EmployeeFacialPhotoCaptureRegistrar::confirmationContext(',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $form
            );
        }

        $this->assertStringNotContainsString(
            "Tab::make('Biometria facial')",
            $form
        );

        $this->assertStringContainsString(
            "Tab::make('Foto cadastral')",
            $form
        );
    }

    public function test_create_action_confirms_and_registers_the_photo_after_employee_creation(): void
    {
        $page = $this->pageSource();

        foreach (
            [
                '->databaseTransaction()',
                '->mutateDataUsing(',
                '->after(',
                "'photo_capture'",
                "'photo_capture_receipt'",
                'ConfirmFacialPhotoPreviewUseCase::class',
                'creationConfirmationContext()',
                'Gate::authorize(',
                "'ManageFacialPhoto:EmployeeRecord'",
                "'manageFacialPhoto'",
                '->registerFromCreation(',
                'FacialPhotoSource::Webcam',
                'FacialPhotoSource::FileUpload',
                "'foto-facial-camera-'",
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $page
            );
        }
    }

    public function test_create_action_does_not_mix_profile_photo_with_biometrics(): void
    {
        $page = $this->pageSource();

        $this->assertStringNotContainsString(
            "unset(\n                            \$data['photo_path']",
            $page
        );

        $this->assertStringNotContainsString(
            "unset(\n                            \$data['photo_disk']",
            $page
        );
    }

    public function test_registrar_has_separate_create_and_update_confirmation_paths(): void
    {
        $registrar = file_get_contents(
            app_path(
                'Modules/Operations/Infrastructure/Storage/'
                .'EmployeeFacialPhotoCaptureRegistrar.php'
            )
        );

        $this->assertIsString($registrar);

        foreach (
            [
                'public function register(',
                'public function registerFromCreation(',
                'private function registerUsingContext(',
                'creationConfirmationContext()',
                "'employee.create.photo_capture'",
                '$expectedConfirmationContext',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $registrar
            );
        }

        $this->assertSame(
            'employee.create.photo_capture',
            EmployeeFacialPhotoCaptureRegistrar::creationConfirmationContext()
        );
    }

    private function pageSource(): string
    {
        $page = file_get_contents(
            app_path(
                'Modules/Identity/UI/Filament/Resources/'
                .'EmployeeRecords/Pages/ListEmployeeRecords.php'
            )
        );

        $this->assertIsString($page);

        return $page;
    }
}
