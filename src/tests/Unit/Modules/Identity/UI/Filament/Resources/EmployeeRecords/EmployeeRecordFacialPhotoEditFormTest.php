<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\EmployeeRecords;

use Tests\TestCase;

final class EmployeeRecordFacialPhotoEditFormTest extends TestCase
{
    public function test_employee_form_uses_the_visitor_style_facial_capture_in_the_main_tab(): void
    {
        $form = $this->formSource();

        foreach (
            [
                "Tab::make('Funcionário')",
                "Section::make('Dados principais')",
                'FacialPhotoCapture::make(',
                "'photo_capture'",
                "'photo_capture_request_id'",
                "'photo_capture_receipt'",
                "->label('Foto facial')",
                'EmployeeFacialPhotoCaptureRegistrar::confirmationContext(',
                'EmployeeFacialPhotoCaptureRegistrar::creationConfirmationContext()',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $form
            );
        }
    }

    public function test_create_and_edit_forms_do_not_use_a_separate_biometric_capture_tab(): void
    {
        $form = $this->formSource();

        $this->assertStringNotContainsString(
            "Tab::make('Biometria facial')",
            $form
        );
    }

    public function test_profile_photo_remains_separate_from_facial_biometrics(): void
    {
        $form = $this->formSource();

        foreach (
            [
                "Tab::make('Foto cadastral')",
                "FileUpload::make('photo_path')",
                "->label('Foto cadastral')",
                'Não é utilizada como biometria facial.',
                'Não é utilizada nos fluxos de reconhecimento facial.',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $form
            );
        }
    }

    public function test_form_does_not_use_the_old_intermediate_facial_action(): void
    {
        $form = $this->formSource();

        foreach (
            [
                'UpdateEmployeeFacialPhotoAction::make(',
                "'Capturar / atualizar foto facial'",
                'iconButton: false',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $form
            );
        }
    }

    private function formSource(): string
    {
        $form = file_get_contents(
            app_path(
                'Modules/Identity/UI/Filament/Resources/'
                .'EmployeeRecords/Schemas/EmployeeRecordForm.php'
            )
        );

        $this->assertIsString(
            $form
        );

        return $form;
    }
}
