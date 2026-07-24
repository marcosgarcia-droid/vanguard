<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use Tests\TestCase;

final class VisitorRecordPhotoCaptureFormTest extends TestCase
{
    public function test_create_form_uses_compact_photo_modal(): void
    {
        $form = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/'
                .'VisitorRecords/Schemas/VisitorRecordForm.php'
            )
        );

        $this->assertIsString($form);

        $this->assertStringContainsString(
            "Hidden::make('photo_path')",
            $form
        );

        $this->assertStringContainsString(
            "FacialPhotoCapture::make('photo_capture')",
            $form
        );

        $this->assertStringContainsString(
            "->visibleOn('create')",
            $form
        );

        $this->assertStringContainsString(
            "FileUpload::make('photo_path')",
            $form
        );

        $this->assertStringContainsString(
            "->visibleOn('edit')",
            $form
        );

        $this->assertStringContainsString(
            'Group::make()',
            $form
        );

        $this->assertStringContainsString(
            'Grid::make(4)',
            $form
        );

        $this->assertStringContainsString(
            "'lg' => 2",
            $form
        );

        $this->assertStringContainsString(
            "'lg' => 4",
            $form
        );

        $this->assertStringNotContainsString(
            "FacialPhotoCapture::make('camera_photo')",
            $form
        );
    }

    public function test_main_fields_follow_the_operational_visual_order(): void
    {
        $form = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/'
                .'VisitorRecords/Schemas/VisitorRecordForm.php'
            )
        );

        $this->assertIsString($form);

        $positions = [
            'full_name' => strpos(
                $form,
                "TextInput::make('full_name')"
            ),
            'preferred_name' => strpos(
                $form,
                "TextInput::make('preferred_name')"
            ),
            'organization_id' => strpos(
                $form,
                "Select::make('organization_id')"
            ),
            'partner_id' => strpos(
                $form,
                "Select::make('partner_id')"
            ),
            'birth_date' => strpos(
                $form,
                "DatePicker::make('birth_date')"
            ),
            'status' => strpos(
                $form,
                "Select::make('status')"
            ),
        ];

        foreach ($positions as $field => $position) {
            $this->assertNotFalse(
                $position,
                "Campo {$field} não encontrado no formulário."
            );
        }

        $this->assertTrue(
            $positions['full_name']
                < $positions['preferred_name']
        );

        $this->assertTrue(
            $positions['preferred_name']
                < $positions['organization_id']
        );

        $this->assertTrue(
            $positions['organization_id']
                < $positions['partner_id']
        );

        $this->assertTrue(
            $positions['partner_id']
                < $positions['birth_date']
        );

        $this->assertTrue(
            $positions['birth_date']
                < $positions['status']
        );
    }

    public function test_create_action_stores_and_removes_temporary_photo_state(): void
    {
        $page = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/'
                .'VisitorRecords/Pages/ListVisitorRecords.php'
            )
        );

        $this->assertIsString($page);

        $this->assertStringContainsString(
            "['photo_capture'] ?? null",
            $page
        );

        $this->assertStringContainsString(
            "unset(\$data['photo_capture']);",
            $page
        );

        $this->assertStringContainsString(
            'VisitorPhotoUploadStorage::class',
            $page
        );

        $this->assertStringContainsString(
            "\$data['photo_path']",
            $page
        );
    }
}
