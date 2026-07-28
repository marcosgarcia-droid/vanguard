<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Images\Receipts;

use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceipt;
use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceiptCodec;
use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceiptException;
use Illuminate\Contracts\Encryption\Encrypter;
use JsonException;
use Throwable;

final readonly class LaravelEncryptedFacialPhotoPreviewReceiptCodec implements FacialPhotoPreviewReceiptCodec
{
    public function __construct(
        private Encrypter $encrypter,
    ) {}

    public function encode(
        FacialPhotoPreviewReceipt $receipt
    ): string {
        try {
            $payload = json_encode(
                $receipt->toPayload(),
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
            );

            $encoded = $this->encrypter->encrypt(
                $payload,
                false
            );

            if (
                ! is_string($encoded)
                || trim($encoded) === ''
            ) {
                throw new JsonException(
                    'The encrypted receipt is empty.'
                );
            }

            return $encoded;
        } catch (Throwable $exception) {
            throw FacialPhotoPreviewReceiptException::issuanceFailed(
                $exception
            );
        }
    }

    public function decode(
        string $encodedReceipt
    ): FacialPhotoPreviewReceipt {
        if (trim($encodedReceipt) === '') {
            throw FacialPhotoPreviewReceiptException::invalid();
        }

        try {
            $decrypted = $this->encrypter->decrypt(
                $encodedReceipt,
                false
            );

            if (! is_string($decrypted)) {
                throw new JsonException(
                    'The decrypted receipt is not a string.'
                );
            }

            $payload = json_decode(
                $decrypted,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (! is_array($payload)) {
                throw new JsonException(
                    'The decrypted receipt payload is invalid.'
                );
            }

            return FacialPhotoPreviewReceipt::fromPayload(
                $payload
            );
        } catch (Throwable $exception) {
            throw FacialPhotoPreviewReceiptException::invalid(
                $exception
            );
        }
    }
}
