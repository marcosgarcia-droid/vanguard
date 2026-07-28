<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Preview\Receipts;

interface FacialPhotoPreviewReceiptCodec
{
    public function encode(
        FacialPhotoPreviewReceipt $receipt
    ): string;

    /**
     * @throws FacialPhotoPreviewReceiptException
     */
    public function decode(
        string $encodedReceipt
    ): FacialPhotoPreviewReceipt;
}
