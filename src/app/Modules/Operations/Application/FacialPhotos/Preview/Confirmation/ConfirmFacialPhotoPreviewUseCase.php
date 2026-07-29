<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation;

use App\Modules\Operations\Application\FacialPhotos\Preview\PreviewFacialPhotoUseCase;
use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceiptCodec;
use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceiptException;
use Throwable;

final readonly class ConfirmFacialPhotoPreviewUseCase
{
    public function __construct(
        private FacialPhotoPreviewReceiptCodec $receiptCodec,
        private PreviewFacialPhotoUseCase $previewFacialPhoto,
    ) {}

    /**
     * @throws ConfirmFacialPhotoPreviewException
     */
    public function execute(
        ConfirmFacialPhotoPreviewCommand $command
    ): ConfirmFacialPhotoPreviewResult {
        try {
            $receipt = $this->receiptCodec->decode(
                $command->encodedReceipt
            );
        } catch (
            FacialPhotoPreviewReceiptException $exception
        ) {
            throw ConfirmFacialPhotoPreviewException::invalidReceipt(
                $exception
            );
        }

        if (
            $receipt->hasExpired(
                $command->confirmedAt
            )
        ) {
            throw ConfirmFacialPhotoPreviewException::expiredReceipt();
        }

        if (
            ! hash_equals(
                $receipt->statePath,
                $command->expectedStatePath
            )
            || $receipt->userId
                !== $command->userId
        ) {
            throw ConfirmFacialPhotoPreviewException::contextMismatch();
        }

        if (
            ! is_file($command->absolutePath)
            || ! is_readable($command->absolutePath)
        ) {
            throw ConfirmFacialPhotoPreviewException::sourceFileUnavailable();
        }

        try {
            $preview =
                $this->previewFacialPhoto->execute(
                    $command->absolutePath
                );
        } catch (Throwable $exception) {
            throw ConfirmFacialPhotoPreviewException::analysisFailed(
                $exception
            );
        }

        $currentFingerprint =
            $preview->fingerprint;

        if (! is_string($currentFingerprint)) {
            throw ConfirmFacialPhotoPreviewException::photoNoLongerUsable();
        }

        $currentFingerprint =
            strtolower(
                $currentFingerprint
            );

        if (
            ! hash_equals(
                $receipt->fingerprint,
                $currentFingerprint
            )
        ) {
            throw ConfirmFacialPhotoPreviewException::photoChanged();
        }

        if (! $preview->canUsePhoto()) {
            throw ConfirmFacialPhotoPreviewException::photoNoLongerUsable();
        }

        return new ConfirmFacialPhotoPreviewResult(
            fingerprint: $currentFingerprint,
            decision: $preview->decision,
            confirmationKey: hash(
                'sha256',
                $command->encodedReceipt
            ),
            confirmationContext: $receipt->statePath,
        );
    }
}
