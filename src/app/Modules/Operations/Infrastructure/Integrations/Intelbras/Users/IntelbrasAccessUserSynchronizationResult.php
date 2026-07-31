<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Users;

use InvalidArgumentException;

final readonly class IntelbrasAccessUserSynchronizationResult
{
    public string $externalUserId;

    public ?string $payloadFingerprint;

    public string $message;

    private function __construct(
        public IntelbrasAccessUserSynchronizationStatus $status,
        string $externalUserId,
        ?string $payloadFingerprint,
        string $message,
    ) {
        $normalizedExternalUserId = trim($externalUserId);

        if (
            $normalizedExternalUserId === ''
            || strlen($normalizedExternalUserId) > 64
            || preg_match(
                '/^[A-Za-z0-9._:-]+$/D',
                $normalizedExternalUserId
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'O identificador externo da sincronização é inválido.'
            );
        }

        $normalizedMessage = trim($message);

        if (
            $normalizedMessage === ''
            || strlen($normalizedMessage) > 255
            || preg_match(
                '/[\x00-\x1F\x7F]/',
                $normalizedMessage
            ) === 1
        ) {
            throw new InvalidArgumentException(
                'A mensagem da sincronização é inválida.'
            );
        }

        if (
            $status === IntelbrasAccessUserSynchronizationStatus::Blocked
            && $payloadFingerprint !== null
        ) {
            throw new InvalidArgumentException(
                'Uma sincronização bloqueada não pode possuir fingerprint.'
            );
        }

        if (
            $status === IntelbrasAccessUserSynchronizationStatus::Simulated
            && (
                ! is_string($payloadFingerprint)
                || preg_match(
                    '/^[a-f0-9]{64}$/D',
                    $payloadFingerprint
                ) !== 1
            )
        ) {
            throw new InvalidArgumentException(
                'A sincronização simulada exige uma fingerprint SHA-256.'
            );
        }

        $this->externalUserId = $normalizedExternalUserId;
        $this->payloadFingerprint = $payloadFingerprint;
        $this->message = $normalizedMessage;
    }

    public static function blocked(
        string $externalUserId
    ): self {
        return new self(
            status: IntelbrasAccessUserSynchronizationStatus::Blocked,
            externalUserId: $externalUserId,
            payloadFingerprint: null,
            message: 'A sincronização Intelbras está desativada por segurança.',
        );
    }

    public static function simulated(
        string $externalUserId,
        string $payloadFingerprint,
    ): self {
        return new self(
            status: IntelbrasAccessUserSynchronizationStatus::Simulated,
            externalUserId: $externalUserId,
            payloadFingerprint: $payloadFingerprint,
            message: 'Sincronização Intelbras simulada sem comunicação com equipamento.',
        );
    }

    public function isBlocked(): bool
    {
        return $this->status
            === IntelbrasAccessUserSynchronizationStatus::Blocked;
    }

    public function isSimulated(): bool
    {
        return $this->status
            === IntelbrasAccessUserSynchronizationStatus::Simulated;
    }

    public function wasTransportAttempted(): bool
    {
        return false;
    }

    /**
     * @return array{
     *     status: string,
     *     status_label: string,
     *     external_user_id: string,
     *     transport_attempted: false,
     *     payload_fingerprint: ?string,
     *     message: string
     * }
     */
    public function toSafeArray(): array
    {
        return [
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'external_user_id' => $this->externalUserId,
            'transport_attempted' => false,
            'payload_fingerprint' => $this->payloadFingerprint,
            'message' => $this->message,
        ];
    }
}
