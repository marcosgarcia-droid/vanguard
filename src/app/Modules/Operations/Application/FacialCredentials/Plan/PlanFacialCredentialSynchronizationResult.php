<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Plan;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityResolution;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialPlan;
use JsonException;
use LogicException;

final readonly class PlanFacialCredentialSynchronizationResult
{
    private function __construct(
        public FacialCredentialSynchronizationPlanningReason $reason,
        public IntelbrasFacialCredentialCompatibilityResolution $compatibility,
        public IntelbrasFacialCredentialOperation $operation,
        public ?IntelbrasFacialCredentialPlan $plan,
    ) {
        if ($reason->isReady()) {
            if (
                $plan === null
                || ! $compatibility->isCompatible()
                || ! $compatibility->supportsOperation($operation)
            ) {
                throw new LogicException(
                    'Um planejamento pronto exige um plano compatível.'
                );
            }

            return;
        }

        if ($plan !== null) {
            throw new LogicException(
                'Um planejamento bloqueado não pode possuir plano.'
            );
        }
    }

    public static function ready(
        IntelbrasFacialCredentialCompatibilityResolution $compatibility,
        IntelbrasFacialCredentialPlan $plan,
    ): self {
        return new self(
            reason: FacialCredentialSynchronizationPlanningReason::Ready,
            compatibility: $compatibility,
            operation: $plan->operation,
            plan: $plan,
        );
    }

    public static function blocked(
        FacialCredentialSynchronizationPlanningReason $reason,
        IntelbrasFacialCredentialCompatibilityResolution $compatibility,
        IntelbrasFacialCredentialOperation $operation,
    ): self {
        if ($reason->isReady()) {
            throw new LogicException(
                'O motivo ready não representa um bloqueio.'
            );
        }

        return new self(
            reason: $reason,
            compatibility: $compatibility,
            operation: $operation,
            plan: null,
        );
    }

    public function isReady(): bool
    {
        return $this->reason->isReady();
    }

    /**
     * Uso interno para idempotência e persistência.
     *
     * Este valor não deve ser exibido na interface.
     *
     * @throws JsonException
     */
    public function planFingerprint(): ?string
    {
        return $this->plan?->safeFingerprint();
    }

    /**
     * @return array{
     *     ready: bool,
     *     reason: string,
     *     reason_label: string,
     *     operation: string,
     *     item_count: int,
     *     compatibility: array<string, mixed>
     * }
     */
    public function toSafeArray(): array
    {
        return [
            'ready' => $this->isReady(),
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'operation' => $this->operation->value,
            'item_count' => $this->plan?->itemCount() ?? 0,
            'compatibility' => $this->compatibility->toSafeArray(),
        ];
    }
}
