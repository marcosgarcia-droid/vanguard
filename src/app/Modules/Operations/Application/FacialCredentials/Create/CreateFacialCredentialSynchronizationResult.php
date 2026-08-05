<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Create;

use App\Modules\Operations\Application\FacialCredentials\Plan\FacialCredentialSynchronizationPlanningReason;
use LogicException;

final readonly class CreateFacialCredentialSynchronizationResult
{
    private function __construct(
        public CreateFacialCredentialSynchronizationReason $reason,
        public ?string $synchronizationId,
        public ?int $version,
        public ?FacialCredentialSynchronizationPlanningReason $planningReason,
    ) {
        if ($reason->isSuccessful()) {
            if (
                $synchronizationId === null
                || $version === null
                || $version < 1
                || $planningReason !== null
            ) {
                throw new LogicException(
                    'Um resultado bem-sucedido exige intenção e versão.'
                );
            }

            return;
        }

        if (
            $synchronizationId !== null
            || $version !== null
        ) {
            throw new LogicException(
                'Um resultado bloqueado não pode possuir intenção.'
            );
        }

        if (
            $reason !==
                CreateFacialCredentialSynchronizationReason::PlanningBlocked
            && $planningReason !== null
        ) {
            throw new LogicException(
                'O motivo do planejamento só pode acompanhar um bloqueio do plano.'
            );
        }
    }

    public static function created(
        string $synchronizationId,
        int $version,
    ): self {
        return new self(
            reason: CreateFacialCredentialSynchronizationReason::Created,
            synchronizationId: $synchronizationId,
            version: $version,
            planningReason: null,
        );
    }

    public static function reused(
        string $synchronizationId,
        int $version,
    ): self {
        return new self(
            reason: CreateFacialCredentialSynchronizationReason::Reused,
            synchronizationId: $synchronizationId,
            version: $version,
            planningReason: null,
        );
    }

    public static function blocked(
        CreateFacialCredentialSynchronizationReason $reason,
        ?FacialCredentialSynchronizationPlanningReason $planningReason = null,
    ): self {
        if ($reason->isSuccessful()) {
            throw new LogicException(
                'Um resultado bloqueado exige um motivo de bloqueio.'
            );
        }

        return new self(
            reason: $reason,
            synchronizationId: null,
            version: null,
            planningReason: $planningReason,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->reason->isSuccessful();
    }

    public function wasCreated(): bool
    {
        return $this->reason
            === CreateFacialCredentialSynchronizationReason::Created;
    }

    public function wasReused(): bool
    {
        return $this->reason
            === CreateFacialCredentialSynchronizationReason::Reused;
    }

    /**
     * @return array{
     *     successful: bool,
     *     created: bool,
     *     reused: bool,
     *     reason: string,
     *     reason_label: string,
     *     synchronization_id: string|null,
     *     version: int|null,
     *     planning_reason: string|null
     * }
     */
    public function toSafeArray(): array
    {
        return [
            'successful' => $this->isSuccessful(),
            'created' => $this->wasCreated(),
            'reused' => $this->wasReused(),
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'synchronization_id' => $this->synchronizationId,
            'version' => $this->version,
            'planning_reason' => $this->planningReason?->value,
        ];
    }
}
