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
                'FacialPhotoPreviewReceiptCodec::class',
                'new FacialPhotoPreviewReceipt(',
                '$result->canUsePhoto()',
                'receipt: $receipt',
                'confirmationContext(',
                'getConfirmationContext()',
                'statePath: $this->getConfirmationContext()',
                'getReceiptStatePath()',
                'RECEIPT_TTL_MINUTES',
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
                'fingerprint: $result->fingerprint,',
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

    public function test_view_presents_safe_preview_analysis_states(): void
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
                "analysisState: 'idle'",
                'x-on:visitor-photo-preview-completed.window',
                'x-on:visitor-photo-preview-failed.window',
                'x-on:visitor-photo-preview-reset.window',
                'handlePreviewCompleted($event)',
                'handlePreviewFailed($event)',
                'handlePreviewReset($event)',
                'Resultado da análise',
                'Analisando a foto...',
                'Foto aprovada',
                'Foto precisa ser refeita',
                'Validação inconclusiva',
                'Usar e validar depois',
                'analysisResult?.issues',
                'x-text="issue.guidance"',
                'x-bind:disabled="! canUsePhoto()"',
                'receiptStatePath: @js($field->getReceiptStatePath())',
                'analysisReceipt: null',
                'typeof detail.receipt',
                'await $wire.set(',
                'this.receiptStatePath',
                'this.analysisReceipt',
                'A análise é temporária',
                'technical_analysis_passed',
                'facial_validation_performed',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $view
            );
        }

        foreach (
            [
                'A análise automática de qualidade será adicionada',
                'x-bind:disabled="! uploaded || uploading"',
                'analysisFingerprint',
                'detail.fingerprint',
                'analysisResult?.metrics',
                'face_count',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $view
            );
        }
    }

    public function test_analysis_panel_uses_side_layout_and_precedes_the_controls(): void
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
                'vanguard-facial-photo-analysis-grid',
                'grid-template-columns:',
                'minmax(20rem, 22rem)',
                'style="display: none;"',
                'aria-hidden="true"',
                "analysisState === 'idle'",
                "analysisState === 'uploading'",
                "analysisState === 'analyzing'",
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $view
            );
        }

        $analysisPosition = strpos(
            $view,
            'Resultado da análise'
        );

        $cameraButtonPosition = strpos(
            $view,
            'Usar câmera'
        );

        $this->assertIsInt(
            $analysisPosition
        );

        $this->assertIsInt(
            $cameraButtonPosition
        );

        $this->assertTrue(
            $analysisPosition < $cameraButtonPosition,
            'O painel de análise deve aparecer antes dos controles da coluna direita.'
        );

        $this->assertStringNotContainsString(
            'lg:grid-cols-[minmax(0,1fr)_22rem]',
            $view
        );
    }

    public function test_result_is_hidden_until_analysis_finishes_and_actions_stay_below(): void
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
                'vanguard-facial-photo-analysis-grid--single',
                'vanguard-facial-photo-bottom-actions',
                'vanguard-facial-photo-final-actions',
                "'approved'",
                "'rejected'",
                "'inconclusive'",
                "'failed'",
                '].includes(analysisState)',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $view
            );
        }

        $analysisPosition = strpos(
            $view,
            'Resultado da análise'
        );

        $bottomActionsPosition = strrpos(
            $view,
            'class="vanguard-facial-photo-bottom-actions"'
        );

        $cameraButtonPosition = strpos(
            $view,
            'Usar câmera'
        );

        $canvasPosition = strpos(
            $view,
            'x-ref="canvas"'
        );

        $this->assertIsInt(
            $analysisPosition
        );

        $this->assertIsInt(
            $bottomActionsPosition
        );

        $this->assertIsInt(
            $cameraButtonPosition
        );

        $this->assertIsInt(
            $canvasPosition
        );

        $this->assertTrue(
            $analysisPosition < $bottomActionsPosition
        );

        $this->assertTrue(
            $bottomActionsPosition < $cameraButtonPosition
        );

        $this->assertTrue(
            $cameraButtonPosition < $canvasPosition
        );
    }

    public function test_action_buttons_share_the_footer_and_results_use_status_symbols(): void
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
                'justify-content: center',
                'gap: 0.75rem',
                '> div:not(.vanguard-facial-photo-final-actions)',
                'display: contents',
                '✅',
                '❌',
                '⚠️',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $view
            );
        }

        $footerPosition = strrpos(
            $view,
            'class="vanguard-facial-photo-bottom-actions"'
        );

        $chooseAnotherPosition = strpos(
            $view,
            'Escolher outra',
            $footerPosition
        );

        $backPosition = strpos(
            $view,
            'Voltar',
            $footerPosition
        );

        $primaryPosition = strpos(
            $view,
            'x-text="primaryActionLabel()"',
            $footerPosition
        );

        $this->assertIsInt(
            $footerPosition
        );

        $this->assertIsInt(
            $chooseAnotherPosition
        );

        $this->assertIsInt(
            $backPosition
        );

        $this->assertIsInt(
            $primaryPosition
        );

        $this->assertTrue(
            $footerPosition < $chooseAnotherPosition
        );

        $this->assertTrue(
            $chooseAnotherPosition < $backPosition
        );

        $this->assertTrue(
            $backPosition < $primaryPosition
        );
    }

    public function test_modal_preserves_portrait_media_and_orders_status_before_buttons(): void
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
                'vanguard-facial-photo-frame',
                'aspect-ratio: 4 / 5',
                'vanguard-facial-photo-media',
                'vanguard-facial-photo-placeholder',
                'vanguard-facial-photo-guide',
                'vanguard-facial-photo-result-panel',
                'vanguard-facial-photo-result-card--approved',
                'vanguard-facial-photo-result-card--rejected',
                'vanguard-facial-photo-result-card--inconclusive',
                'vanguard-facial-photo-result-card--failed',
                'vanguard-facial-photo-result-symbol',
                'order: 1',
                'order: 2',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $view
            );
        }

        $this->assertSame(
            4,
            substr_count(
                $view,
                'class="vanguard-facial-photo-result-symbol"'
            )
        );
    }

    public function test_modal_centers_heading_actions_and_places_symbols_beside_issue_titles(): void
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
                'vanguard-facial-photo-modal-heading',
                'vanguard-facial-photo-modal-description',
                'justify-content: center',
                'vanguard-facial-photo-result-item',
                'vanguard-facial-photo-result-item__content',
                'align-items: flex-start',
                'text-align: center',
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $view
            );
        }

        $this->assertSame(
            2,
            substr_count(
                $view,
                'class="vanguard-facial-photo-result-item"'
            )
        );

        $this->assertSame(
            2,
            substr_count(
                $view,
                'class="vanguard-facial-photo-result-item__content"'
            )
        );

        foreach (
            [
                '❌',
                '⚠️',
            ] as $symbol
        ) {
            $symbolPosition = strpos(
                $view,
                $symbol
            );

            $issueLabelPosition = strpos(
                $view,
                'x-text="issue.label"',
                $symbolPosition
            );

            $this->assertIsInt(
                $symbolPosition
            );

            $this->assertIsInt(
                $issueLabelPosition
            );

            $this->assertTrue(
                $symbolPosition < $issueLabelPosition
            );
        }
    }

    public function test_closing_the_modal_discards_only_an_unconfirmed_preview(): void
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

        $handlerStart =
            strpos(
                $view,
                'handleModalClosed(event) {'
            );

        $handlerEnd =
            strpos(
                $view,
                'destroy() {',
                $handlerStart === false
                    ? 0
                    : $handlerStart
            );

        $this->assertNotFalse(
            $handlerStart
        );

        $this->assertNotFalse(
            $handlerEnd
        );

        $handler =
            substr(
                $view,
                (int) $handlerStart,
                (int) $handlerEnd
                    - (int) $handlerStart
            );

        $this->assertStringContainsString(
            'detail.id !== this.modalId',
            $handler
        );

        $this->assertStringContainsString(
            'this.previewUrl',
            $handler
        );

        $this->assertStringContainsString(
            '! this.confirmed',
            $handler
        );

        $this->assertStringContainsString(
            'this.clearPhoto()',
            $handler
        );

        $this->assertStringContainsString(
            'x-on:close-modal.window="handleModalClosed($event)"',
            $view
        );

        $this->assertStringNotContainsString(
            'x-on:close-modal.window="stopCamera()"',
            $view
        );

        $clearStart =
            strpos(
                $view,
                'clearPhoto() {'
            );

        $clearEnd =
            strpos(
                $view,
                'stopCamera() {',
                $clearStart === false
                    ? 0
                    : $clearStart
            );

        $this->assertNotFalse(
            $clearStart
        );

        $this->assertNotFalse(
            $clearEnd
        );

        $clearPhoto =
            substr(
                $view,
                (int) $clearStart,
                (int) $clearEnd
                    - (int) $clearStart
            );

        $this->assertStringContainsString(
            'URL.revokeObjectURL',
            $clearPhoto
        );

        $this->assertStringContainsString(
            'this.resetAnalysis()',
            $clearPhoto
        );

        $this->assertStringContainsString(
            '$wire.set(',
            $clearPhoto
        );

        $this->assertStringContainsString(
            'this.statePath',
            $clearPhoto
        );

        $resetStart =
            strpos(
                $view,
                "resetAnalysis(state = 'idle') {"
            );

        $resetEnd =
            strpos(
                $view,
                'handlePreviewCompleted(event) {',
                $resetStart === false
                    ? 0
                    : $resetStart
            );

        $this->assertNotFalse(
            $resetStart
        );

        $this->assertNotFalse(
            $resetEnd
        );

        $resetAnalysis =
            substr(
                $view,
                (int) $resetStart,
                (int) $resetEnd
                    - (int) $resetStart
            );

        $this->assertStringContainsString(
            'this.clearReceiptState()',
            $resetAnalysis
        );
    }
}
