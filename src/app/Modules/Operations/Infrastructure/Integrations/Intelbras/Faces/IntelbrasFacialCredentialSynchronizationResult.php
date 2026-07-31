<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use InvalidArgumentException;
use JsonException;

final readonly class IntelbrasFacialCredentialSynchronizationResult
{
    private function __construct(
        public IntelbrasFacialCredentialSynchronizationStatus $status,
        public IntelbrasFacialCredentialTransport $transport,
        public IntelbrasFacialCredentialOperation $operation,
        public int $itemCount,
        public ?string $requestFingerprint,
        public ?IntelbrasFacialCredentialResponse $response,
        public bool $transportAttempted,
    ) {
        if ($itemCount < 1 || $itemCount > 10) {
            throw new InvalidArgumentException(
                'A quantidade de credenciais faciais é inválida.'
            );
        }

        if ($transportAttempted) {
            throw new InvalidArgumentException(
                'Este contrato não permite transporte físico.'
            );
        }

        if (
            $status
                === IntelbrasFacialCredentialSynchronizationStatus::Blocked
            && (
                $requestFingerprint !== null
                || $response !== null
            )
        ) {
            throw new InvalidArgumentException(
                'Uma sincronização bloqueada não pode conter execução simulada.'
            );
        }

        if (
            $status
                === IntelbrasFacialCredentialSynchronizationStatus::Simulated
            && (
                $requestFingerprint === null
                || preg_match(
                    '/^[a-f0-9]{64}$/D',
                    $requestFingerprint
                ) !== 1
                || $response === null
            )
        ) {
            throw new InvalidArgumentException(
                'O resultado simulado está incompleto.'
            );
        }
    }

    public static function blocked(
        IntelbrasFacialCredentialRequest $request
    ): self {
        return new self(
            status: IntelbrasFacialCredentialSynchronizationStatus::Blocked,
            transport: $request->transport,
            operation: $request->operation,
            itemCount: count($request->items),
            requestFingerprint: null,
            response: null,
            transportAttempted: false,
        );
    }

    /**
     * @throws JsonException
     */
    public static function simulated(
        IntelbrasFacialCredentialRequest $request,
        IntelbrasFacialCredentialResponse $response,
    ): self {
        return new self(
            status: IntelbrasFacialCredentialSynchronizationStatus::Simulated,
            transport: $request->transport,
            operation: $request->operation,
            itemCount: count($request->items),
            requestFingerprint: $request->payloadFingerprint(),
            response: $response,
            transportAttempted: false,
        );
    }

    public function wasSimulatedSuccessfully(): bool
    {
        return $this->status
            === IntelbrasFacialCredentialSynchronizationStatus::Simulated
            && $this->response?->wasSuccessful() === true;
    }

    public function isDuplicatePhoto(): bool
    {
        return $this->response?->isDuplicatePhoto() === true;
    }

    public function requiresAttention(): bool
    {
        return ! $this->wasSimulatedSuccessfully();
    }

    /**
     * @return array{
     *     status: string,
     *     transport: string,
     *     operation: string,
     *     item_count: int,
     *     request_fingerprint: ?string,
     *     response: ?array{
     *         status: string,
     *         code: ?int,
     *         fail_codes: list<int>,
     *         message: string
     *     },
     *     transport_attempted: false,
     *     message: string
     * }
     */
    public function toSafeArray(): array
    {
        return [
            'status' => $this->status->value,
            'transport' => $this->transport->value,
            'operation' => $this->operation->value,
            'item_count' => $this->itemCount,
            'request_fingerprint' => $this->requestFingerprint,
            'response' => $this->response?->toSafeArray(),
            'transport_attempted' => false,
            'message' => $this->status->safeMessage(),
        ];
    }
}
