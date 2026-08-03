<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

use InvalidArgumentException;
use JsonException;

final readonly class IntelbrasFacialCredentialSynchronizationResult
{
    private function __construct(
        public IntelbrasFacialCredentialSynchronizationStatus $status,
        public IntelbrasFacialCredentialCompatibilityProfile $compatibility,
        public IntelbrasFacialCredentialOperation $operation,
        public int $itemCount,
        public ?string $planFingerprint,
        public ?IntelbrasFacialCredentialResponse $response,
        public bool $transportAttempted,
    ) {
        if (
            $itemCount < 1
            || $itemCount > $compatibility->maxItems
        ) {
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
                $planFingerprint !== null
                || $response !== null
            )
        ) {
            throw new InvalidArgumentException(
                'Uma sincronização bloqueada não pode conter simulação.'
            );
        }

        if (
            $status
                === IntelbrasFacialCredentialSynchronizationStatus::Simulated
            && (
                $planFingerprint === null
                || preg_match(
                    '/^[a-f0-9]{64}$/D',
                    $planFingerprint
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
        IntelbrasFacialCredentialPlan $plan
    ): self {
        return new self(
            status: IntelbrasFacialCredentialSynchronizationStatus::Blocked,
            compatibility: $plan->compatibility,
            operation: $plan->operation,
            itemCount: $plan->itemCount(),
            planFingerprint: null,
            response: null,
            transportAttempted: false,
        );
    }

    /**
     * @throws JsonException
     */
    public static function simulated(
        IntelbrasFacialCredentialPlan $plan,
        IntelbrasFacialCredentialResponse $response,
    ): self {
        return new self(
            status: IntelbrasFacialCredentialSynchronizationStatus::Simulated,
            compatibility: $plan->compatibility,
            operation: $plan->operation,
            itemCount: $plan->itemCount(),
            planFingerprint: $plan->safeFingerprint(),
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
     *     compatibility: array{
     *         family: string,
     *         model: string,
     *         firmware: string,
     *         max_items: int,
     *         supports_replacement: bool,
     *         requires_display_name: bool
     *     },
     *     operation: string,
     *     item_count: int,
     *     plan_fingerprint: ?string,
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
            'compatibility' => $this->compatibility->toSafeArray(),
            'operation' => $this->operation->value,
            'item_count' => $this->itemCount,
            'plan_fingerprint' => $this->planFingerprint,
            'response' => $this->response?->toSafeArray(),
            'transport_attempted' => false,
            'message' => $this->status->safeMessage(),
        ];
    }
}
