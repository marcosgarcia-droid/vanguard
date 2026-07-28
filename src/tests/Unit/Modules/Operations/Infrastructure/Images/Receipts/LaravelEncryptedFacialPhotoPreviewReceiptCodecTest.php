<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\Receipts;

use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceipt;
use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceiptException;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoPreviewDecision;
use App\Modules\Operations\Infrastructure\Images\Receipts\LaravelEncryptedFacialPhotoPreviewReceiptCodec;
use DateTimeImmutable;
use Illuminate\Encryption\Encrypter;
use PHPUnit\Framework\TestCase;

final class LaravelEncryptedFacialPhotoPreviewReceiptCodecTest extends TestCase
{
    private Encrypter $encrypter;

    private LaravelEncryptedFacialPhotoPreviewReceiptCodec $codec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encrypter = new Encrypter(
            str_repeat('k', 32),
            'AES-256-CBC'
        );

        $this->codec =
            new LaravelEncryptedFacialPhotoPreviewReceiptCodec(
                $this->encrypter
            );
    }

    public function test_it_round_trips_an_encrypted_receipt(): void
    {
        $receipt = $this->receipt();

        $encoded =
            $this->codec->encode(
                $receipt
            );

        $decoded =
            $this->codec->decode(
                $encoded
            );

        $this->assertEquals(
            $receipt,
            $decoded
        );
    }

    public function test_the_encoded_receipt_does_not_expose_its_payload(): void
    {
        $receipt = $this->receipt();

        $encoded =
            $this->codec->encode(
                $receipt
            );

        $this->assertStringNotContainsString(
            $receipt->fingerprint,
            $encoded
        );

        $this->assertStringNotContainsString(
            $receipt->statePath,
            $encoded
        );

        $this->assertStringNotContainsString(
            $receipt->decision->value,
            $encoded
        );
    }

    public function test_it_rejects_a_tampered_receipt(): void
    {
        $encoded =
            $this->codec->encode(
                $this->receipt()
            );

        $lastCharacter =
            substr($encoded, -1);

        $tampered =
            substr($encoded, 0, -1)
            .($lastCharacter === 'a'
                ? 'b'
                : 'a');

        $this->expectException(
            FacialPhotoPreviewReceiptException::class
        );

        $this->codec->decode(
            $tampered
        );
    }

    public function test_it_rejects_an_empty_receipt(): void
    {
        $this->expectException(
            FacialPhotoPreviewReceiptException::class
        );

        $this->codec->decode('   ');
    }

    public function test_it_rejects_an_encrypted_invalid_payload(): void
    {
        $encoded =
            $this->encrypter->encrypt(
                json_encode(
                    [
                        'version' => 999,
                    ],
                    JSON_THROW_ON_ERROR
                ),
                false
            );

        $this->expectException(
            FacialPhotoPreviewReceiptException::class
        );

        $this->codec->decode(
            $encoded
        );
    }

    public function test_failures_expose_only_a_safe_message(): void
    {
        try {
            $this->codec->decode(
                'invalid-receipt'
            );

            $this->fail(
                'An invalid receipt should fail.'
            );
        } catch (
            FacialPhotoPreviewReceiptException $exception
        ) {
            $this->assertSame(
                'A confirmação temporária da foto é inválida ou expirou. '
                    .'Analise a imagem novamente.',
                $exception->getMessage()
            );

            $this->assertStringNotContainsString(
                'MAC',
                $exception->getMessage()
            );
        }
    }

    private function receipt(): FacialPhotoPreviewReceipt
    {
        return new FacialPhotoPreviewReceipt(
            fingerprint: str_repeat('a', 64),
            decision: FacialPhotoPreviewDecision::Approved,
            statePath: 'mountedActions.0.data.photo_capture',
            userId: 10,
            expiresAt: new DateTimeImmutable(
                '2026-07-28T16:00:00-03:00'
            ),
        );
    }
}
