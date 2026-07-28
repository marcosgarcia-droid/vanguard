<?php

declare(strict_types=1);

namespace App\Modules\Operations\UI\Filament\Forms\Components;

use App\Modules\Operations\Application\FacialPhotos\Preview\FacialPhotoPreviewResult;
use App\Modules\Operations\Application\FacialPhotos\Preview\PreviewFacialPhotoUseCase;
use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceipt;
use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceiptCodec;
use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Http\UploadedFile;
use Throwable;

final class FacialPhotoCapture extends Field
{
    private const RECEIPT_TTL_MINUTES = 10;

    protected string|Closure|null $confirmationContext = null;

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

    public function getReceiptStatePath(): string
    {
        return $this->getStatePath()
            .'_receipt';
    }

    public function confirmationContext(
        string|Closure $context
    ): static {
        $this->confirmationContext =
            $context;

        return $this;
    }

    public function getConfirmationContext(): string
    {
        $context = $this->evaluate(
            $this->confirmationContext
        );

        if (
            ! is_string($context)
            || trim($context) === ''
        ) {
            return $this->getStatePath();
        }

        return trim($context);
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

            $receipt =
                $this->receiptFor(
                    $result
                );

            $this->getLivewire()->dispatch(
                'visitor-photo-preview-completed',
                id: $this->getModalId(),
                statePath: $this->getStatePath(),
                receipt: $receipt,
                result: $result->presentation(),
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->dispatchPreviewFailure();
        }
    }

    private function receiptFor(
        FacialPhotoPreviewResult $result
    ): ?string {
        $fingerprint =
            $result->fingerprint;

        if (
            ! $result->canUsePhoto()
            || ! is_string($fingerprint)
        ) {
            return null;
        }

        return app(
            FacialPhotoPreviewReceiptCodec::class
        )->encode(
            new FacialPhotoPreviewReceipt(
                fingerprint: strtolower(
                    $fingerprint
                ),
                decision: $result->decision,
                statePath: $this->getConfirmationContext(),
                userId: self::authenticatedUserId(),
                expiresAt: now()
                    ->addMinutes(
                        self::RECEIPT_TTL_MINUTES
                    )
                    ->toDateTimeImmutable(),
            )
        );
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

    private static function authenticatedUserId(): ?int
    {
        $userId = auth()->id();

        return is_numeric($userId)
            ? (int) $userId
            : null;
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
