<?php

declare(strict_types=1);

namespace App\Modules\Operations\UI\Filament\Forms\Components;

use App\Modules\Operations\Application\FacialPhotos\Preview\PreviewFacialPhotoUseCase;
use Filament\Forms\Components\Field;
use Illuminate\Http\UploadedFile;
use Throwable;

final class FacialPhotoCapture extends Field
{
    protected string $view =
        'filament.forms.components.facial-photo-capture';

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->dehydrated()
            ->nullable()
            ->afterStateUpdated(function (): void {
                $this->previewCurrentState();
            });
    }

    public function getModalId(): string
    {
        return 'visitor-photo-'
            .str($this->getId())
                ->replace(['.', ':'], '-')
                ->slug();
    }

    private function previewCurrentState(): void
    {
        $upload = self::uploadedFileFrom(
            $this->getState()
        );

        if (! $upload instanceof UploadedFile) {
            $this->dispatchPreviewReset();

            return;
        }

        $absolutePath =
            $upload->getRealPath();

        if (
            ! is_string($absolutePath)
            || trim($absolutePath) === ''
        ) {
            $this->dispatchPreviewFailure();

            return;
        }

        try {
            $result = app(
                PreviewFacialPhotoUseCase::class
            )->execute(
                $absolutePath
            );

            $this->getLivewire()->dispatch(
                'visitor-photo-preview-completed',
                id: $this->getModalId(),
                statePath: $this->getStatePath(),
                fingerprint: $result->fingerprint,
                result: $result->presentation(),
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->dispatchPreviewFailure();
        }
    }

    private function dispatchPreviewReset(): void
    {
        $this->getLivewire()->dispatch(
            'visitor-photo-preview-reset',
            id: $this->getModalId(),
            statePath: $this->getStatePath(),
        );
    }

    private function dispatchPreviewFailure(): void
    {
        $this->getLivewire()->dispatch(
            'visitor-photo-preview-failed',
            id: $this->getModalId(),
            statePath: $this->getStatePath(),
            message: 'Não foi possível analisar a foto. '
                .'Escolha outra imagem ou tente novamente.',
        );
    }

    private static function uploadedFileFrom(
        mixed $value
    ): ?UploadedFile {
        if ($value instanceof UploadedFile) {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach ($value as $file) {
            if ($file instanceof UploadedFile) {
                return $file;
            }
        }

        return null;
    }
}
