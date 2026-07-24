<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Forms\Components;

use App\Modules\Operations\UI\Filament\Forms\Components\FacialPhotoCapture;
use Tests\TestCase;

final class FacialPhotoCaptureTest extends TestCase
{
    public function test_it_uses_the_dedicated_photo_modal_view(): void
    {
        $field = FacialPhotoCapture::make('photo_capture');

        $this->assertSame(
            'filament.forms.components.facial-photo-capture',
            $field->getView()
        );
    }

    public function test_view_uses_a_modal_camera_and_native_livewire_upload(): void
    {
        $view = file_get_contents(
            resource_path(
                'views/filament/forms/components/'
                .'facial-photo-capture.blade.php'
            )
        );

        $this->assertIsString($view);

        $this->assertStringContainsString(
            '<x-filament::modal',
            $view
        );

        $this->assertStringContainsString(
            'wire:model="{{ $statePath }}"',
            $view
        );

        $this->assertStringContainsString(
            'navigator.mediaDevices.getUserMedia',
            $view
        );

        $this->assertStringContainsString(
            'canvas.toBlob',
            $view
        );

        $this->assertStringContainsString(
            'new DataTransfer()',
            $view
        );

        $this->assertStringContainsString(
            'Selecionar arquivo',
            $view
        );

        $this->assertStringContainsString(
            'Usar esta foto',
            $view
        );

        $this->assertStringContainsString(
            "'close-modal'",
            $view
        );

        $this->assertStringContainsString(
            "'visitor-photo-selected'",
            $view
        );

        $this->assertStringContainsString(
            'selectedPreviewUrl',
            $view
        );

        $this->assertStringContainsString(
            'photoReady',
            $view
        );

        $this->assertStringContainsString(
            'x-show="! cameraActive && ! previewUrl"',
            $view
        );

        $this->assertStringContainsString(
            'color="success"',
            $view
        );

        $this->assertStringContainsString(
            "'Foto adicionada'",
            $view
        );

        $this->assertStringContainsString(
            "'Alterar foto'",
            $view
        );

        $this->assertStringContainsString(
            'width: 160px',
            $view
        );

        $this->assertStringContainsString(
            'height: 200px',
            $view
        );

        $this->assertStringContainsString(
            'object-fit: cover',
            $view
        );

        $this->assertStringNotContainsString(
            '$wire.upload(',
            $view
        );
    }
}
