<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Preview\Receipts;

use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceipt;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoPreviewDecision;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FacialPhotoPreviewReceiptTest extends TestCase
{
    public function test_it_serializes_and_restores_a_usable_receipt(): void
    {
        $receipt = new FacialPhotoPreviewReceipt(
            fingerprint: str_repeat('a', 64),
            decision: FacialPhotoPreviewDecision::Approved,
            statePath: 'mountedActions.0.data.photo_capture',
            userId: 10,
            expiresAt: new DateTimeImmutable(
                '2026-07-28T16:00:00-03:00'
            ),
        );

        $restored =
            FacialPhotoPreviewReceipt::fromPayload(
                $receipt->toPayload()
            );

        $this->assertEquals(
            $receipt,
            $restored
        );

        $this->assertSame(
            FacialPhotoPreviewReceipt::VERSION,
            $receipt->toPayload()['version']
        );
    }

    public function test_it_accepts_an_inconclusive_usable_decision(): void
    {
        $receipt = new FacialPhotoPreviewReceipt(
            fingerprint: str_repeat('b', 64),
            decision: FacialPhotoPreviewDecision::Inconclusive,
            statePath: 'data.photo_capture',
            userId: null,
            expiresAt: new DateTimeImmutable(
                '2026-07-28T16:00:00-03:00'
            ),
        );

        $this->assertSame(
            FacialPhotoPreviewDecision::Inconclusive,
            $receipt->decision
        );
    }

    public function test_it_rejects_a_rejected_preview_decision(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new FacialPhotoPreviewReceipt(
            fingerprint: str_repeat('c', 64),
            decision: FacialPhotoPreviewDecision::Rejected,
            statePath: 'data.photo_capture',
            userId: 10,
            expiresAt: new DateTimeImmutable(
                '2026-07-28T16:00:00-03:00'
            ),
        );
    }

    public function test_it_rejects_an_invalid_fingerprint(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new FacialPhotoPreviewReceipt(
            fingerprint: 'not-a-sha-256',
            decision: FacialPhotoPreviewDecision::Approved,
            statePath: 'data.photo_capture',
            userId: 10,
            expiresAt: new DateTimeImmutable(
                '2026-07-28T16:00:00-03:00'
            ),
        );
    }

    public function test_it_rejects_invalid_context_values(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new FacialPhotoPreviewReceipt(
            fingerprint: str_repeat('d', 64),
            decision: FacialPhotoPreviewDecision::Approved,
            statePath: "data.photo_capture\ninvalid",
            userId: 0,
            expiresAt: new DateTimeImmutable(
                '2026-07-28T16:00:00-03:00'
            ),
        );
    }

    public function test_it_detects_expiration_at_the_exact_boundary(): void
    {
        $receipt = new FacialPhotoPreviewReceipt(
            fingerprint: str_repeat('e', 64),
            decision: FacialPhotoPreviewDecision::Approved,
            statePath: 'data.photo_capture',
            userId: 10,
            expiresAt: new DateTimeImmutable(
                '2026-07-28T16:00:00-03:00'
            ),
        );

        $this->assertFalse(
            $receipt->hasExpired(
                new DateTimeImmutable(
                    '2026-07-28T15:59:59-03:00'
                )
            )
        );

        $this->assertTrue(
            $receipt->hasExpired(
                new DateTimeImmutable(
                    '2026-07-28T16:00:00-03:00'
                )
            )
        );
    }

    public function test_it_rejects_an_unsupported_payload_version(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        FacialPhotoPreviewReceipt::fromPayload([
            'version' => 999,
            'fingerprint' => str_repeat('f', 64),
            'decision' => 'approved',
            'state_path' => 'data.photo_capture',
            'user_id' => 10,
            'expires_at' => '2026-07-28T16:00:00-03:00',
        ]);
    }
}
