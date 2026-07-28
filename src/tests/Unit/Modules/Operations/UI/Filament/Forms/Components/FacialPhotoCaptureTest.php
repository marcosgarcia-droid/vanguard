<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Forms\Components;

use App\Modules\Operations\UI\Filament\Forms\Components\FacialPhotoCapture;
use Tests\TestCase;

final class FacialPhotoCaptureTest extends TestCase
{
    public function test_it_uses_the_dedicated_photo_modal_view(): void
    {
        $field = FacialPhotoCapture::make(
            'photo_capture'
        );

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

        $this->assertIsString(
            $view
        );

        foreach (
            [
                '<x-filament::modal',
                'wire:model="{{ $statePath }}"',
                'navigator.mediaDevices.getUserMedia',
                'canvas.toBlob',
                'new DataTransfer()',
                'Selecionar arquivo',
                'Usar esta foto',
                "'close-modal'",
                "'visitor-photo-selected'",
                'selectedPreviewUrl',
                'photoReady',
                'x-show="! cameraActive && ! previewUrl"',
                'color="success"',
                "'Foto adicionada'",
                "'Alterar foto'",
                'width: 160px',
                'height: 200px',
                'object-fit: cover',
                '$field->getModalId()',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $view
            );
        }

        $this->assertStringNotContainsString(
            '$wire.upload(',
            $view
        );

        $this->assertStringNotContainsString(
            '.str($getId())',
            $view
        );
    }

    public function test_component_previews_the_temporary_upload_without_persisting_it(): void
    {
        $component = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Forms/'
                .'Components/FacialPhotoCapture.php'
            )
        );

        $this->assertIsString(
            $component
        );

        foreach (
            [
                '->afterStateUpdated(',
                'PreviewFacialPhotoUseCase::class',
                'getRealPath()',
                "'visitor-photo-preview-completed'",
                "'visitor-photo-preview-failed'",
                "'visitor-photo-preview-reset'",
                '$result->presentation()',
                '$result->fingerprint',
                'getModalId()',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $component
            );
        }

        foreach (
            [
                'VisitorFacialPhotoCaptureRegistrar',
                'DB::',
                'FacialPhotoRecord::',
                'VisitorRecord::query()',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $component
            );
        }
    }
}
