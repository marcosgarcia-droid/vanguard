<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Preview\Receipts;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoPreviewDecision;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

final readonly class FacialPhotoPreviewReceipt
{
    public const VERSION = 1;

    public function __construct(
        public string $fingerprint,
        public FacialPhotoPreviewDecision $decision,
        public string $statePath,
        public ?int $userId,
        public DateTimeImmutable $expiresAt,
    ) {
        if (
            preg_match(
                '/\A[a-f0-9]{64}\z/',
                $this->fingerprint
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'The facial photo preview fingerprint must be a SHA-256 value.'
            );
        }

        if (
            ! in_array(
                $this->decision,
                [
                    FacialPhotoPreviewDecision::Approved,
                    FacialPhotoPreviewDecision::Inconclusive,
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Only a usable facial photo preview can produce a receipt.'
            );
        }

        $normalizedStatePath = trim(
            $this->statePath
        );

        if (
            $normalizedStatePath === ''
            || strlen($normalizedStatePath) > 512
            || preg_match(
                '/[\x00-\x1F\x7F]/',
                $normalizedStatePath
            ) === 1
        ) {
            throw new InvalidArgumentException(
                'The facial photo preview state path is invalid.'
            );
        }

        if (
            $this->userId !== null
            && $this->userId < 1
        ) {
            throw new InvalidArgumentException(
                'The facial photo preview user identifier is invalid.'
            );
        }
    }

    /**
     * @return array{
     *     version: int,
     *     fingerprint: string,
     *     decision: string,
     *     state_path: string,
     *     user_id: int|null,
     *     expires_at: string
     * }
     */
    public function toPayload(): array
    {
        return [
            'version' => self::VERSION,
            'fingerprint' => $this->fingerprint,
            'decision' => $this->decision->value,
            'state_path' => $this->statePath,
            'user_id' => $this->userId,
            'expires_at' => $this->expiresAt->format(
                DATE_ATOM
            ),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromPayload(
        array $payload
    ): self {
        try {
            if (
                ($payload['version'] ?? null)
                !== self::VERSION
            ) {
                throw new InvalidArgumentException(
                    'Unsupported facial photo preview receipt version.'
                );
            }

            $fingerprint =
                $payload['fingerprint']
                    ?? null;

            $decisionValue =
                $payload['decision']
                    ?? null;

            $statePath =
                $payload['state_path']
                    ?? null;

            $userId =
                $payload['user_id']
                    ?? null;

            $expiresAtValue =
                $payload['expires_at']
                    ?? null;

            if (
                ! is_string($fingerprint)
                || ! is_string($decisionValue)
                || ! is_string($statePath)
                || (
                    $userId !== null
                    && ! is_int($userId)
                )
                || ! is_string($expiresAtValue)
            ) {
                throw new InvalidArgumentException(
                    'Malformed facial photo preview receipt payload.'
                );
            }

            $decision =
                FacialPhotoPreviewDecision::tryFrom(
                    $decisionValue
                );

            if (
                ! $decision
                    instanceof FacialPhotoPreviewDecision
            ) {
                throw new InvalidArgumentException(
                    'Unknown facial photo preview decision.'
                );
            }

            $expiresAt =
                DateTimeImmutable::createFromFormat(
                    DATE_ATOM,
                    $expiresAtValue
                );

            if (
                ! $expiresAt
                    instanceof DateTimeImmutable
                || $expiresAt->format(DATE_ATOM)
                    !== $expiresAtValue
            ) {
                throw new InvalidArgumentException(
                    'Invalid facial photo preview receipt expiration.'
                );
            }

            return new self(
                fingerprint: $fingerprint,
                decision: $decision,
                statePath: $statePath,
                userId: $userId,
                expiresAt: $expiresAt,
            );
        } catch (Throwable $exception) {
            if (
                $exception
                instanceof InvalidArgumentException
            ) {
                throw $exception;
            }

            throw new InvalidArgumentException(
                'Malformed facial photo preview receipt payload.',
                previous: $exception
            );
        }
    }

    public function hasExpired(
        DateTimeImmutable $now
    ): bool {
        return $this->expiresAt <= $now;
    }
}
