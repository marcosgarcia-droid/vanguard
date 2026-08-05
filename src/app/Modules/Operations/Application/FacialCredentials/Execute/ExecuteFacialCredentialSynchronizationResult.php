<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Execute;

use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationAttemptStatus;
use LogicException;

final readonly class ExecuteFacialCredentialSynchronizationResult
{
    private function __construct(
        public ExecuteFacialCredentialSynchronizationReason $reason,
        public ?string $synchronizationId,
        public ?int $attemptNumber,
        public ?FacialCredentialSynchronizationAttemptStatus $status,
        public ?string $provider,
        public ?string $scenario,
        public ?string $failureCode,
    ) {
        if ($reason->isSuccessful()) {
            if (
                $synchronizationId === null
                || $attemptNumber === null
                || $attemptNumber < 1
                || $status === null
                || $provider === null
                || trim($provider) === ''
            ) {
                throw new LogicException(
                    'Um resultado executado exige uma tentativa válida.'
                );
            }

            return;
        }

        if (
            $attemptNumber !== null
            || $status !== null
            || $provider !== null
            || $scenario !== null
            || $failureCode !== null
        ) {
            throw new LogicException(
                'Um resultado sem tentativa não pode possuir dados de execução.'
            );
        }
    }

    public static function executed(
        string $synchronizationId,
        int $attemptNumber,
        FacialCredentialSynchronizationAttemptStatus $status,
        string $provider,
        ?string $scenario,
        ?string $failureCode,
    ): self {
        return new self(
            reason: ExecuteFacialCredentialSynchronizationReason::Executed,
            synchronizationId: $synchronizationId,
            attemptNumber: $attemptNumber,
            status: $status,
            provider: $provider,
            scenario: $scenario,
            failureCode: $failureCode,
        );
    }

    public static function reused(
        string $synchronizationId,
        int $attemptNumber,
        FacialCredentialSynchronizationAttemptStatus $status,
        string $provider,
        ?string $scenario,
        ?string $failureCode,
    ): self {
        return new self(
            reason: ExecuteFacialCredentialSynchronizationReason::Reused,
            synchronizationId: $synchronizationId,
            attemptNumber: $attemptNumber,
            status: $status,
            provider: $provider,
            scenario: $scenario,
            failureCode: $failureCode,
        );
    }

    public static function withoutAttempt(
        ExecuteFacialCredentialSynchronizationReason $reason,
        ?string $synchronizationId = null,
    ): self {
        if ($reason->isSuccessful()) {
            throw new LogicException(
                'Um resultado bem-sucedido exige uma tentativa.'
            );
        }

        return new self(
            reason: $reason,
            synchronizationId: $synchronizationId,
            attemptNumber: null,
            status: null,
            provider: null,
            scenario: null,
            failureCode: null,
        );
    }

    public function wasExecuted(): bool
    {
        return $this->reason
            === ExecuteFacialCredentialSynchronizationReason::Executed;
    }

    public function wasReused(): bool
    {
        return $this->reason
            === ExecuteFacialCredentialSynchronizationReason::Reused;
    }

    /**
     * @return array{
     *     successful: bool,
     *     executed: bool,
     *     reused: bool,
     *     reason: string,
     *     reason_label: string,
     *     synchronization_id: string|null,
     *     attempt_number: int|null,
     *     status: string|null,
     *     provider: string|null,
     *     scenario: string|null,
     *     failure_code: string|null
     * }
     */
    public function toSafeArray(): array
    {
        return [
            'successful' => $this->reason->isSuccessful(),
            'executed' => $this->wasExecuted(),
            'reused' => $this->wasReused(),
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'synchronization_id' => $this->synchronizationId,
            'attempt_number' => $this->attemptNumber,
            'status' => $this->status?->value,
            'provider' => $this->provider,
            'scenario' => $this->scenario,
            'failure_code' => $this->failureCode,
        ];
    }
}
